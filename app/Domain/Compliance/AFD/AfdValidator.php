<?php

namespace App\Domain\Compliance\AFD;

class AfdValidator
{
    /**
     * Valida formal e estruturalmente o conteúdo de um arquivo AFD segundo a Portaria 671 MTE.
     *
     * @param  string  $content  Conteúdo bruto do arquivo
     * @return array{is_valid: bool, errors: array<string>, total_records: int}
     */
    public function validate(string $content): array
    {
        $errors = [];
        $rawLines = explode("\r\n", $content);

        // Remove linha vazia residual ao final do arquivo gerado por CRLF
        if (end($rawLines) === '') {
            array_pop($rawLines);
        }

        if (count($rawLines) < 2) {
            return [
                'is_valid' => false,
                'errors' => ['O arquivo deve conter no mínimo Cabeçalho (Tipo 1) e Trailer (Tipo 9).'],
                'total_records' => 0,
            ];
        }

        $header = $rawLines[0];
        $trailer = end($rawLines);
        $bodyLines = array_slice($rawLines, 1, -1);

        // 1. Validação do Cabeçalho (Tipo 1) - 236 caracteres
        if (strlen($header) !== 236) {
            $errors[] = sprintf('Cabeçalho (Tipo 1) possui %d caracteres; o leiaute MTE exige exatamente 236.', strlen($header));
        } else {
            if (substr($header, 0, 9) !== '000000000') {
                $errors[] = 'NSR do Cabeçalho deve ser obrigatoriamente 000000000.';
            }
            if ($header[9] !== '1') {
                $errors[] = 'Tipo de registro do cabeçalho deve ser 1.';
            }
            if (! in_array($header[10], ['1', '2'])) {
                $errors[] = 'Identificador do empregador deve ser 1 (CNPJ) ou 2 (CPF).';
            }
            if (! ctype_digit(substr($header, 11, 14))) {
                $errors[] = 'CNPJ/CPF do empregador deve conter apenas dígitos numéricos.';
            }

            $dtInicio = substr($header, 204, 8);
            $dtFim = substr($header, 212, 8);
            $dtGeracao = substr($header, 220, 8);

            if (! $this->isValidDate($dtInicio)) {
                $errors[] = "Data inicial no cabeçalho inválida: {$dtInicio}";
            }
            if (! $this->isValidDate($dtFim)) {
                $errors[] = "Data final no cabeçalho inválida: {$dtFim}";
            }
            if (! $this->isValidDate($dtGeracao)) {
                $errors[] = "Data de geração no cabeçalho inválida: {$dtGeracao}";
            }
        }

        // 2. Validação dos Registros de Marcação (Tipo 3) - 101 caracteres
        $previousNsr = 0;
        $type3Count = 0;

        foreach ($bodyLines as $idx => $line) {
            $lineNum = $idx + 2;
            if (strlen($line) !== 101) {
                $errors[] = sprintf('Linha %d possui %d caracteres; o registro Tipo 3 exige exatamente 101.', $lineNum, strlen($line));

                continue;
            }

            $nsrRaw = substr($line, 0, 9);
            if (! ctype_digit($nsrRaw)) {
                $errors[] = "Linha {$lineNum}: NSR não numérico: {$nsrRaw}";

                continue;
            }

            $nsr = (int) $nsrRaw;
            if ($nsr <= $previousNsr) {
                $errors[] = "Linha {$lineNum}: NSR ({$nsr}) quebrou a sequência monotônica ascendente (anterior: {$previousNsr}).";
            }
            $previousNsr = $nsr;

            if ($line[9] !== '3') {
                $errors[] = "Linha {$lineNum}: Tipo de registro inválido (esperado 3, encontrado '{$line[9]}').";
            }

            $dtMarcacao = substr($line, 10, 8);
            $hrMarcacao = substr($line, 18, 4);
            $offset = substr($line, 22, 4);
            $cpf = substr($line, 26, 11);
            $hash = substr($line, 37, 64);

            if (! $this->isValidDate($dtMarcacao)) {
                $errors[] = "Linha {$lineNum}: Data de marcação inválida ({$dtMarcacao}).";
            }
            if (! $this->isValidTime($hrMarcacao)) {
                $errors[] = "Linha {$lineNum}: Horário de marcação inválido ({$hrMarcacao}).";
            }
            if (! preg_match('/^[+-]\d{3}$/', $offset)) {
                $errors[] = "Linha {$lineNum}: Fuso horário deve seguir o formato [+-]HHMM ou [+-]HH: {$offset}";
            }
            if (! ctype_digit($cpf) || strlen($cpf) !== 11) {
                $errors[] = "Linha {$lineNum}: CPF deve conter 11 dígitos numéricos: {$cpf}";
            }
            if (! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
                $errors[] = "Linha {$lineNum}: Hash SHA-256 inválido: {$hash}";
            }

            $type3Count++;
        }

        // 3. Validação do Trailer (Tipo 9) - 63 caracteres
        if (strlen($trailer) !== 63) {
            $errors[] = sprintf('Trailer (Tipo 9) possui %d caracteres; o leiaute MTE exige exatamente 63.', strlen($trailer));
        } else {
            if (substr($trailer, 0, 9) !== '999999999') {
                $errors[] = 'NSR do Trailer deve ser 999999999.';
            }
            if ($trailer[9] !== '9') {
                $errors[] = 'Tipo de registro do trailer deve ser 9.';
            }

            $qtdTipo3Trailer = (int) substr($trailer, 10, 9);
            if ($qtdTipo3Trailer !== $type3Count) {
                $errors[] = "Trailer indica {$qtdTipo3Trailer} registros Tipo 3, mas o arquivo contém {$type3Count}.";
            }

            $totalLinhasTrailer = (int) substr($trailer, 46, 9);
            $totalLinhasReais = count($rawLines);
            if ($totalLinhasTrailer !== $totalLinhasReais) {
                $errors[] = "Trailer indica total de {$totalLinhasTrailer} linhas, mas o arquivo possui {$totalLinhasReais}.";
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'total_records' => $type3Count,
        ];
    }

    protected function isValidDate(string $d): bool
    {
        if (strlen($d) !== 8 || ! ctype_digit($d)) {
            return false;
        }
        $day = (int) substr($d, 0, 2);
        $month = (int) substr($d, 2, 2);
        $year = (int) substr($d, 4, 4);

        return checkdate($month, $day, $year);
    }

    protected function isValidTime(string $t): bool
    {
        if (strlen($t) !== 4 || ! ctype_digit($t)) {
            return false;
        }
        $hour = (int) substr($t, 0, 2);
        $minute = (int) substr($t, 2, 2);

        return ($hour >= 0 && $hour <= 23) && ($minute >= 0 && $minute <= 59);
    }
}
