<?php

namespace App\Domain\Compliance\AFD;

use App\Domain\Compliance\Fiscal\Services\FiscalHashService;

class AfdValidator
{
    protected FiscalHashService $fiscalHashService;

    public function __construct(?FiscalHashService $fiscalHashService = null)
    {
        $this->fiscalHashService = $fiscalHashService ?: new FiscalHashService;
    }

    /**
     * Valida formal e estruturalmente o conteúdo de um arquivo AFD segundo a Portaria 671 MTE (leiaute vigente REP-P).
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
                $errors[] = "Hora de geração no cabeçalho inválida: {$hrGeracao}";
            }
        }

        // 2. Validação dos Registros de Corpo (Tipos 2, 4, 5, 6, 7)
        $previousNsr = 0;
        $countTipo2 = 0;
        $countTipo3 = 0;
        $countTipo4 = 0;
        $countTipo5 = 0;
        $countTipo6 = 0;
        $countTipo7 = 0;

        foreach ($bodyLines as $idx => $line) {
            $lineNum = $idx + 2;
            $len = strlen($line);

            if ($len < 10) {
                $errors[] = "Linha {$lineNum}: Registro com tamanho insuficiente ({$len} caracteres).";

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
                case '2': // Identificação do Empregador - 203 caracteres com CRC-16
                    $countTipo2++;
                    if ($len !== 203) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 2 possui {$len} caracteres; exigido: 203.";
                    } else {
                        $prefix = substr($line, 0, 199);
                        $expectedCrc = $this->fiscalHashService->calculateCrc16($prefix);
                        $actualCrc = substr($line, 199, 4);
                        if (strtoupper($actualCrc) !== strtoupper($expectedCrc)) {
                            $errors[] = "Linha {$lineNum}: CRC-16 divergente no registro Tipo 2 (calculado: {$expectedCrc}, arquivo: {$actualCrc}).";
                        }
                    }
                    break;

                case '3': // Marcação REP-C/REP-A (Não aplicável ao REP-P)
                    $countTipo3++;
                    $errors[] = "Linha {$lineNum}: O modelo REP-P deve utilizar Registro Tipo 7 para marcações, não Tipo 3.";
                    break;

                case '4': // Ajuste do Relógio - 49 caracteres com CRC-16
                    $countTipo4++;
                    if ($len !== 49) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 4 possui {$len} caracteres; exigido: 49.";
                    } else {
                        $prefix = substr($line, 0, 45);
                        $expectedCrc = $this->fiscalHashService->calculateCrc16($prefix);
                        $actualCrc = substr($line, 45, 4);
                        if (strtoupper($actualCrc) !== strtoupper($expectedCrc)) {
                            $errors[] = "Linha {$lineNum}: CRC-16 divergente no registro Tipo 4 (calculado: {$expectedCrc}, arquivo: {$actualCrc}).";
                        }
                    }
                    break;

                case '5': // Empregado - 189 caracteres com CRC-16
                    $countTipo5++;
                    if ($len !== 189) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 5 possui {$len} caracteres; exigido: 189.";
                    } else {
                        $prefix = substr($line, 0, 185);
                        $expectedCrc = $this->fiscalHashService->calculateCrc16($prefix);
                        $actualCrc = substr($line, 185, 4);
                        if (strtoupper($actualCrc) !== strtoupper($expectedCrc)) {
                            $errors[] = "Linha {$lineNum}: CRC-16 divergente no registro Tipo 5 (calculado: {$expectedCrc}, arquivo: {$actualCrc}).";
                        }
                    }
                    break;

                case '6': // Eventos Sensíveis - 136 caracteres
                    $countTipo6++;
                    if ($len !== 136) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 6 possui {$len} caracteres; exigido: 136.";
                    }
                    break;

                case '7': // Marcação de Ponto REP-P - 137 caracteres
                    $countTipo7++;
                    if ($len !== 137) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 7 possui {$len} caracteres; o leiaute MTE exige exatamente 137.";
                    } else {
                        $cpf = substr($line, 34, 12);
                        $collector = substr($line, 70, 2);
                        $punchType = $line[72];
                        $hash = substr($line, 73, 64);

                        if (! ctype_digit($cpf)) {
                            $errors[] = "Linha {$lineNum}: CPF deve conter dígitos numéricos no registro Tipo 7: {$cpf}";
                        }
                        if (! ctype_digit($collector)) {
                            $errors[] = "Linha {$lineNum}: Identificador do coletor deve ser numérico: {$collector}";
                        }
                        if (! in_array($punchType, ['0', '1'])) {
                            $errors[] = "Linha {$lineNum}: Tipo de marcação deve ser 0 (online) ou 1 (offline): {$punchType}";
                        }
                        if (! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
                            $errors[] = "Linha {$lineNum}: Hash SHA-256 inválido: {$hash}";
                        }
                    }
                    break;

                default:
                    $errors[] = "Linha {$lineNum}: Tipo de registro inválido ou não suportado para REP-P: '{$tipo}'.";
                    break;
            }
        }

        // 3. Validação do Trailer (Tipo 9) - exatamente 73 caracteres
        if (strlen($trailer) !== 73) {
            $errors[] = sprintf('Trailer (Tipo 9) possui %d caracteres; o leiaute MTE exige exatamente 73.', strlen($trailer));
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
            $qtdTipo7Trailer = (int) substr($trailer, 55, 9);
            $totalLinhasTrailer = (int) substr($trailer, 64, 9);
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
            if ($qtdTipo7Trailer !== $countTipo7) {
                $errors[] = "Trailer indica {$qtdTipo7Trailer} registros Tipo 7, mas o arquivo contém {$countTipo7}.";
            }
            if ($totalLinhasTrailer !== $totalLinhasReais) {
                $errors[] = "Trailer indica total de {$totalLinhasTrailer} linhas, mas o arquivo possui {$totalLinhasReais}.";
            }
        }

        $totalRecords = $countTipo2 + $countTipo3 + $countTipo4 + $countTipo5 + $countTipo6 + $countTipo7;

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'total_records' => $totalRecords,
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
