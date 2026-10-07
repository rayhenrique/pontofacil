<?php

namespace App\Domain\Compliance\AEJ;

class AejValidator
{
    /**
     * Valida formal e estruturalmente o conteúdo de um arquivo AEJ segundo o leiaute oficial
     * do Anexo VI da Portaria 671/2021 MTP (formato delimitado por pipe '|').
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
                'errors' => ['O arquivo AEJ deve conter no mínimo Cabeçalho (Tipo 01) e Trailer (Tipo 99).'],
                'total_records' => 0,
            ];
        }

        $header = $rawLines[0];
        $trailer = end($rawLines);
        $bodyLines = array_slice($rawLines, 1, -1);

        // Contadores reais encontrados no arquivo
        $actualCounts = [
            '01' => 0,
            '02' => 0,
            '03' => 0,
            '04' => 0,
            '05' => 0,
            '06' => 0,
            '07' => 0,
            '08' => 0,
        ];

        // 1. Validação do Cabeçalho (Tipo 01)
        $headerParts = explode('|', $header);
        if ($headerParts[0] !== '01') {
            $errors[] = "A primeira linha do arquivo deve ser o Cabeçalho (Tipo 01). Encontrado: '{$headerParts[0]}'.";
        } elseif (count($headerParts) < 10) {
            $errors[] = sprintf('Cabeçalho (Tipo 01) possui %d campos; o leiaute MTE exige 10 campos.', count($headerParts));
        } else {
            $actualCounts['01']++;
            $tpIdt = $headerParts[1];
            $idtEmp = $headerParts[2];
            $razao = $headerParts[5];
            $dtInicio = $headerParts[6];
            $dtFim = $headerParts[7];
            $dtHoraGer = $headerParts[8];
            $versao = $headerParts[9];

            if (! in_array($tpIdt, ['1', '2'], true)) {
                $errors[] = "Tipo de identificador do empregador no cabeçalho deve ser 1 (CNPJ) ou 2 (CPF). Encontrado: '{$tpIdt}'.";
            }
            if (! ctype_digit($idtEmp)) {
                $errors[] = "CNPJ/CPF do empregador no cabeçalho deve conter apenas dígitos numéricos: '{$idtEmp}'.";
            }
            if (trim($razao) === '') {
                $errors[] = 'Razão social ou nome do empregador não pode ser vazio no cabeçalho.';
            }
            if (! $this->isValidIsoDate($dtInicio)) {
                $errors[] = "Data inicial no cabeçalho inválida (exigido AAAA-MM-dd): '{$dtInicio}'.";
            }
            if (! $this->isValidIsoDate($dtFim)) {
                $errors[] = "Data final no cabeçalho inválida (exigido AAAA-MM-dd): '{$dtFim}'.";
            }
            if (! $this->isValidIsoDateTime($dtHoraGer)) {
                $errors[] = "Data e hora de geração no cabeçalho inválida (exigido AAAA-MM-ddThh:mm:00ZZZZZ): '{$dtHoraGer}'.";
            }
            if (trim($versao) === '') {
                $errors[] = 'Versão do leiaute do AEJ não pode ser vazia no cabeçalho.';
            }
        }

        // 2. Validação dos Registros de Corpo
        foreach ($bodyLines as $idx => $line) {
            $lineNum = $idx + 2;
            $parts = explode('|', $line);
            $tipo = $parts[0] ?? '';

            if (isset($actualCounts[$tipo])) {
                $actualCounts[$tipo]++;
            }

            switch ($tipo) {
                case '02': // REPs utilizados: 02|idRepAej|tpRep|nrRep
                    if (count($parts) < 4) {
                        $errors[] = "Linha {$lineNum}: Registro 02 possui número insuficiente de campos.";
                    } else {
                        if (! ctype_digit($parts[1])) {
                            $errors[] = "Linha {$lineNum}: idRepAej deve ser numérico no registro 02: '{$parts[1]}'.";
                        }
                        if (! in_array($parts[2], ['1', '2', '3'], true)) {
                            $errors[] = "Linha {$lineNum}: tpRep deve ser 1, 2 ou 3 no registro 02: '{$parts[2]}'.";
                        }
                        if (trim($parts[3]) === '') {
                            $errors[] = "Linha {$lineNum}: nrRep não pode ser vazio no registro 02.";
                        }
                    }
                    break;

                case '03': // Vínculos: 03|idtVinculoAej|cpf|nomeEmp
                    if (count($parts) < 4) {
                        $errors[] = "Linha {$lineNum}: Registro 03 possui número insuficiente de campos.";
                    } else {
                        if (! ctype_digit($parts[1])) {
                            $errors[] = "Linha {$lineNum}: idtVinculoAej deve ser numérico no registro 03: '{$parts[1]}'.";
                        }
                        if (! ctype_digit($parts[2]) || strlen($parts[2]) !== 11) {
                            $errors[] = "Linha {$lineNum}: CPF deve conter exatamente 11 dígitos numéricos no registro 03: '{$parts[2]}'.";
                        }
                        if (trim($parts[3]) === '') {
                            $errors[] = "Linha {$lineNum}: nomeEmp não pode ser vazio no registro 03.";
                        }
                    }
                    break;

                case '04': // Horários contratuais: 04|codHorContratual|durJornada|hrEntrada01|hrSaida01...
                    if (count($parts) < 5) {
                        $errors[] = "Linha {$lineNum}: Registro 04 possui número insuficiente de campos (mínimo 5).";
                    } else {
                        if (trim($parts[1]) === '') {
                            $errors[] = "Linha {$lineNum}: codHorContratual não pode ser vazio no registro 04.";
                        }
                        if (! ctype_digit($parts[2])) {
                            $errors[] = "Linha {$lineNum}: durJornada deve ser numérico em minutos no registro 04: '{$parts[2]}'.";
                        }
                        if (! $this->isValidTimeHhmm($parts[3])) {
                            $errors[] = "Linha {$lineNum}: hrEntrada01 inválida no registro 04: '{$parts[3]}'.";
                        }
                        if (! $this->isValidTimeHhmm($parts[4])) {
                            $errors[] = "Linha {$lineNum}: hrSaida01 inválida no registro 04: '{$parts[4]}'.";
                        }
                    }
                    break;

                case '05': // Marcações: 05|idtVinculoAej|dataHoraMarc|numNsr|idRepAej|tpMarc|tipoFonte|motivo
                    if (count($parts) < 7) {
                        $errors[] = "Linha {$lineNum}: Registro 05 possui número insuficiente de campos (mínimo 7).";
                    } else {
                        if (! ctype_digit($parts[1])) {
                            $errors[] = "Linha {$lineNum}: idtVinculoAej deve ser numérico no registro 05: '{$parts[1]}'.";
                        }
                        if (! $this->isValidIsoDateTime($parts[2])) {
                            $errors[] = "Linha {$lineNum}: dataHoraMarc inválida no registro 05: '{$parts[2]}'.";
                        }
                        if (! ctype_digit($parts[3])) {
                            $errors[] = "Linha {$lineNum}: numNsr deve ser numérico no registro 05: '{$parts[3]}'.";
                        }
                        if (! ctype_digit($parts[4])) {
                            $errors[] = "Linha {$lineNum}: idRepAej deve ser numérico no registro 05: '{$parts[4]}'.";
                        }
                        if (! in_array($parts[5], ['E', 'S'], true)) {
                            $errors[] = "Linha {$lineNum}: tpMarc deve ser 'E' ou 'S' no registro 05: '{$parts[5]}'.";
                        }
                        if (! in_array($parts[6], ['1', '2', '3'], true)) {
                            $errors[] = "Linha {$lineNum}: tipoFonte deve ser '1', '2' ou '3' no registro 05: '{$parts[6]}'.";
                        }
                    }
                    break;

                case '06': // Matrícula eSocial: 06|idtVinculoAej|matrEsocial
                    if (count($parts) < 3) {
                        $errors[] = "Linha {$lineNum}: Registro 06 possui número insuficiente de campos.";
                    } else {
                        if (! ctype_digit($parts[1])) {
                            $errors[] = "Linha {$lineNum}: idtVinculoAej deve ser numérico no registro 06: '{$parts[1]}'.";
                        }
                        if (trim($parts[2]) === '') {
                            $errors[] = "Linha {$lineNum}: matrEsocial não pode ser vazia no registro 06.";
                        }
                    }
                    break;

                case '07': // Ausências e Banco de Horas: 07|idtVinculoAej|tipoAusenOuComp|data|qtMinutos
                    if (count($parts) < 5) {
                        $errors[] = "Linha {$lineNum}: Registro 07 possui número insuficiente de campos.";
                    } else {
                        if (! ctype_digit($parts[1])) {
                            $errors[] = "Linha {$lineNum}: idtVinculoAej deve ser numérico no registro 07: '{$parts[1]}'.";
                        }
                        if (! in_array($parts[2], ['1', '2', '3', '4'], true)) {
                            $errors[] = "Linha {$lineNum}: tipoAusenOuComp deve ser 1, 2, 3 ou 4 no registro 07: '{$parts[2]}'.";
                        }
                        if (! $this->isValidIsoDate($parts[3])) {
                            $errors[] = "Linha {$lineNum}: data inválida no registro 07: '{$parts[3]}'.";
                        }
                        if (! is_numeric($parts[4])) {
                            $errors[] = "Linha {$lineNum}: qtMinutos deve ser numérico no registro 07: '{$parts[4]}'.";
                        }
                    }
                    break;

                case '08': // Identificação do PTRP: 08|tpIdtDesenv|idtDesenv|nomeDesenv|nomeSoftware|versaoSoftware
                    if (count($parts) < 6) {
                        $errors[] = "Linha {$lineNum}: Registro 08 possui número insuficiente de campos.";
                    } else {
                        if (! in_array($parts[1], ['1', '2'], true)) {
                            $errors[] = "Linha {$lineNum}: tpIdtDesenv deve ser 1 ou 2 no registro 08: '{$parts[1]}'.";
                        }
                        if (! ctype_digit($parts[2])) {
                            $errors[] = "Linha {$lineNum}: idtDesenv deve ser numérico no registro 08: '{$parts[2]}'.";
                        }
                        if (trim($parts[3]) === '') {
                            $errors[] = "Linha {$lineNum}: nomeDesenv não pode ser vazio no registro 08.";
                        }
                        if (trim($parts[4]) === '') {
                            $errors[] = "Linha {$lineNum}: nomeSoftware não pode ser vazio no registro 08.";
                        }
                        if (trim($parts[5]) === '') {
                            $errors[] = "Linha {$lineNum}: versaoSoftware não pode ser vazia no registro 08.";
                        }
                    }
                    break;

                default:
                    $errors[] = "Linha {$lineNum}: Tipo de registro inválido ou desconhecido no AEJ: '{$tipo}'.";
                    break;
            }
        }

        // 3. Validação do Trailer (Tipo 99)
        $trailerParts = explode('|', $trailer);
        if ($trailerParts[0] !== '99') {
            $errors[] = "A última linha do arquivo deve ser o Trailer (Tipo 99). Encontrado: '{$trailerParts[0]}'.";
        } elseif (count($trailerParts) < 9) {
            $errors[] = sprintf('Trailer (Tipo 99) possui %d campos; o leiaute MTE exige no mínimo 9 campos.', count($trailerParts));
        } else {
            for ($i = 1; $i <= 8; $i++) {
                $code = sprintf('%02d', $i);
                $declared = (int) ($trailerParts[$i] ?? 0);
                $actual = $actualCounts[$code] ?? 0;
                if ($declared !== $actual) {
                    $errors[] = "Trailer indica {$declared} registros Tipo {$code}, mas o arquivo contém {$actual}.";
                }
            }
        }

        $totalRecords = array_sum($actualCounts) + 1;

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'total_records' => $totalRecords,
        ];
    }

    protected function isValidIsoDate(string $d): bool
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    protected function isValidIsoDateTime(string $dt): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00[+-]\d{4}$/', $dt);
    }

    protected function isValidTimeHhmm(string $t): bool
    {
        if (strlen($t) !== 4 || ! ctype_digit($t)) {
            return false;
        }
        $h = (int) substr($t, 0, 2);
        $m = (int) substr($t, 2, 2);

        return ($h >= 0 && $h <= 23) && ($m >= 0 && $m <= 59);
    }
}
