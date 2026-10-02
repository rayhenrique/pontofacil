<?php

namespace App\Domain\Compliance\AEJ;

use Illuminate\Support\Facades\Log;

class AejValidator
{
    /**
     * Valida formal e estruturalmente o conteúdo de um arquivo AEJ segundo a Portaria 671 MTE.
     *
     * @param  string  $content  Conteúdo bruto do arquivo AEJ
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
                'errors' => ['O arquivo AEJ deve conter no mínimo Cabeçalho (Tipo 1) e Trailer (Tipo 9).'],
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
            $hrGeracao = substr($header, 228, 4);

            if (! $this->isValidDate($dtInicio)) {
                $errors[] = "Data inicial no cabeçalho inválida: {$dtInicio}";
            }
            if (! $this->isValidDate($dtFim)) {
                $errors[] = "Data final no cabeçalho inválida: {$dtFim}";
            }
            if (! $this->isValidDate($dtGeracao)) {
                $errors[] = "Data de geração no cabeçalho inválida: {$dtGeracao}";
            }
            if (! $this->isValidTime($hrGeracao)) {
                $errors[] = "Horário de geração no cabeçalho inválido: {$hrGeracao}";
            }
        }

        // 2. Validação dos Registros de Corpo (Tipos 2, 3, 4, 5, 6)
        $previousNsr = 0;
        $countTipo2 = 0;
        $countTipo3 = 0;
        $countTipo4 = 0;
        $countTipo5 = 0;
        $countTipo6 = 0;

        foreach ($bodyLines as $idx => $line) {
            $lineNum = $idx + 2;
            $len = strlen($line);

            if ($len < 10) {
                $errors[] = "Linha {$lineNum} possui comprimento insuficiente ({$len} caracteres).";

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

            $tipo = $line[9];

            switch ($tipo) {
                case '2': // Cadastro de Empregados - 249 caracteres
                    $countTipo2++;
                    if ($len !== 249) {
                        $errors[] = "Linha {$lineNum} (Tipo 2) possui {$len} caracteres; exigido: 249.";
                    } else {
                        $cpf = substr($line, 10, 11);
                        if (! ctype_digit($cpf) || strlen($cpf) !== 11) {
                            $errors[] = "Linha {$lineNum}: CPF inválido no registro Tipo 2: {$cpf}";
                        }
                    }
                    break;

                case '3': // Horários e Escalas - 70 caracteres
                    $countTipo3++;
                    if ($len !== 70) {
                        $errors[] = "Linha {$lineNum} (Tipo 3) possui {$len} caracteres; exigido: 70.";
                    }
                    break;

                case '4': // Marcações e Tratamentos - 48 caracteres
                    $countTipo4++;
                    if ($len !== 48) {
                        $errors[] = "Linha {$lineNum} (Tipo 4) possui {$len} caracteres; exigido: 48.";
                    } else {
                        $dt = substr($line, 10, 8);
                        $hr = substr($line, 18, 4);
                        $cpf = substr($line, 22, 11);
                        $tipoMarcacao = $line[42];
                        $origem = $line[43];

                        if (! $this->isValidDate($dt)) {
                            $errors[] = "Linha {$lineNum}: Data inválida no registro Tipo 4: {$dt}";
                        }
                        if (! $this->isValidTime($hr)) {
                            $errors[] = "Linha {$lineNum}: Horário inválido no registro Tipo 4: {$hr}";
                        }
                        if (! ctype_digit($cpf) || strlen($cpf) !== 11) {
                            $errors[] = "Linha {$lineNum}: CPF inválido no registro Tipo 4: {$cpf}";
                        }
                        if (! in_array($tipoMarcacao, ['E', 'S', 'O'])) {
                            $errors[] = "Linha {$lineNum}: Tipo de marcação inválido (esperado E, S ou O): {$tipoMarcacao}";
                        }
                        if (! in_array($origem, ['O', 'I', 'D'])) {
                            $errors[] = "Linha {$lineNum}: Origem de marcação inválida (esperado O, I ou D): {$origem}";
                        }
                    }
                    break;

                case '5': // Apuração Mensal - 41 caracteres
                    $countTipo5++;
                    if ($len !== 41) {
                        $errors[] = "Linha {$lineNum} (Tipo 5) possui {$len} caracteres; exigido: 41.";
                    } else {
                        $cpf = substr($line, 10, 11);
                        if (! ctype_digit($cpf) || strlen($cpf) !== 11) {
                            $errors[] = "Linha {$lineNum}: CPF inválido no registro Tipo 5: {$cpf}";
                        }
                    }
                    break;

                case '6': // Banco de Horas - 47 caracteres
                    $countTipo6++;
                    if ($len !== 47) {
                        $errors[] = "Linha {$lineNum} (Tipo 6) possui {$len} caracteres; exigido: 47.";
                    } else {
                        $cpf = substr($line, 10, 11);
                        $sinalAnt = $line[21];
                        $sinalFim = $line[40];

                        if (! ctype_digit($cpf) || strlen($cpf) !== 11) {
                            $errors[] = "Linha {$lineNum}: CPF inválido no registro Tipo 6: {$cpf}";
                        }
                        if (! in_array($sinalAnt, ['+', '-'])) {
                            $errors[] = "Linha {$lineNum}: Sinal do saldo anterior deve ser + ou -: {$sinalAnt}";
                        }
                        if (! in_array($sinalFim, ['+', '-'])) {
                            $errors[] = "Linha {$lineNum}: Sinal do saldo final deve ser + ou -: {$sinalFim}";
                        }
                    }
                    break;

                default:
                    $errors[] = "Linha {$lineNum}: Tipo de registro desconhecido: '{$tipo}'.";
                    break;
            }
        }

        // 3. Validação do Trailer (Tipo 9) - 72 caracteres
        if (strlen($trailer) !== 72) {
            $errors[] = sprintf('Trailer (Tipo 9) possui %d caracteres; o leiaute MTE exige exatamente 72.', strlen($trailer));
        } else {
            if (substr($trailer, 0, 9) !== '999999999') {
                $errors[] = 'NSR do Trailer deve ser 999999999.';
            }
            if ($trailer[9] !== '9') {
                $errors[] = 'Tipo de registro do trailer deve ser 9.';
            }

            $qtdTipo2Trailer = (int) substr($trailer, 10, 9);
            $qtdTipo3Trailer = (int) substr($trailer, 19, 9);
            $qtdTipo4Trailer = (int) substr($trailer, 28, 9);
            $qtdTipo5Trailer = (int) substr($trailer, 37, 9);
            $qtdTipo6Trailer = (int) substr($trailer, 46, 9);
            $totalLinhasTrailer = (int) substr($trailer, 55, 9);
            $totalLinhasReais = count($rawLines);

            if ($qtdTipo2Trailer !== $countTipo2) {
                $errors[] = "Trailer indica {$qtdTipo2Trailer} registros Tipo 2, mas o arquivo contém {$countTipo2}.";
            }
            if ($qtdTipo3Trailer !== $countTipo3) {
                $errors[] = "Trailer indica {$qtdTipo3Trailer} registros Tipo 3, mas o arquivo contém {$countTipo3}.";
            }
            if ($qtdTipo4Trailer !== $countTipo4) {
                $errors[] = "Trailer indica {$qtdTipo4Trailer} registros Tipo 4, mas o arquivo contém {$countTipo4}.";
            }
            if ($qtdTipo5Trailer !== $countTipo5) {
                $errors[] = "Trailer indica {$qtdTipo5Trailer} registros Tipo 5, mas o arquivo contém {$countTipo5}.";
            }
            if ($qtdTipo6Trailer !== $countTipo6) {
                $errors[] = "Trailer indica {$qtdTipo6Trailer} registros Tipo 6, mas o arquivo contém {$countTipo6}.";
            }
            if ($totalLinhasTrailer !== $totalLinhasReais) {
                $errors[] = "Trailer indica total de {$totalLinhasTrailer} linhas, mas o arquivo possui {$totalLinhasReais}.";
            }

            // Validação de CRC-32
            $contentBeforeCrc = implode("\r\n", array_slice($rawLines, 0, -1))."\r\n".substr($trailer, 0, 64);
            $expectedCrc = sprintf('%08X', crc32($contentBeforeCrc));
            $actualCrc = substr($trailer, 64, 8);

            if (strtoupper($actualCrc) !== strtoupper($expectedCrc)) {
                $errors[] = "CRC-32 divergente no trailer (calculado: {$expectedCrc}, arquivo: {$actualCrc}).";
            }
        }

        $isValid = empty($errors);

        Log::info('aej.validated', [
            'is_valid' => $isValid,
            'errors_count' => count($errors),
            'total_records' => count($bodyLines),
        ]);

        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'total_records' => count($bodyLines),
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
