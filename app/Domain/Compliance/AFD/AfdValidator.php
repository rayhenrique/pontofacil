<?php

namespace App\Domain\Compliance\AFD;

use App\Domain\Compliance\Fiscal\Services\FiscalHashService;
use App\Domain\Compliance\Signing\CadesSignatureVerifierInterface;
use App\Domain\Compliance\Signing\PendingCadesSignatureVerifier;

class AfdValidator
{
    protected FiscalHashService $fiscalHashService;

    protected CadesSignatureVerifierInterface $signatureVerifier;

    public function __construct(
        ?FiscalHashService $fiscalHashService = null,
        ?CadesSignatureVerifierInterface $signatureVerifier = null,
    ) {
        $this->fiscalHashService = $fiscalHashService
            ?? (function_exists('app') && app()->bound(FiscalHashService::class)
                ? app(FiscalHashService::class)
                : new FiscalHashService);

        $this->signatureVerifier = $signatureVerifier
            ?? (function_exists('app') && app()->bound(CadesSignatureVerifierInterface::class)
                ? app(CadesSignatureVerifierInterface::class)
                : new PendingCadesSignatureVerifier);
    }

    /**
     * Valida formal e estruturalmente o conteúdo de um arquivo AFD segundo a Portaria 671 MTE (leiaute vigente REP-P publicado em 31/07/2026).
     *
     * @param  string  $content  Conteúdo bruto do arquivo
     * @param  string|null  $detachedSignature  Conteúdo binário ou textual do arquivo de assinatura destacada CAdES (.p7s)
     * @return array{is_valid: bool, structurally_valid: bool, structureValid: bool, signature_valid: bool, signatureValid: bool, signature_status: string, signatureStatus: string, is_homologated: bool, isHomologated: bool, homologation_reason: ?string, homologationReason: ?string, reason: ?string, errors: array<string>, total_records: int}
     */
    public function validate(string $content, ?string $detachedSignature = null): array
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
                'structurally_valid' => false,
                'structureValid' => false,
                'signature_valid' => false,
                'signatureValid' => false,
                'signature_status' => 'pending_certificate',
                'signatureStatus' => 'pending_certificate',
                'is_homologated' => false,
                'isHomologated' => false,
                'reason' => 'missing_minimum_records',
                'homologation_reason' => 'missing_minimum_records',
                'homologationReason' => 'missing_minimum_records',
                'errors' => ['O arquivo deve conter no mínimo Cabeçalho (Tipo 1) e Trailer (Tipo 9).'],
                'total_records' => 0,
            ];
        }

        // Verifica marcador oficial de assinatura externa CAdES (.p7s) na última linha
        $lastLine = end($rawLines);
        if (str_starts_with($lastLine, 'ASSINATURA_DIGITAL_EM_ARQUIVO_P7S')) {
            $signatureLine = array_pop($rawLines);
            if (strlen($signatureLine) !== 100) {
                $errors[] = sprintf('Linha de assinatura CAdES possui %d caracteres; o leiaute MTE exige exatamente 100.', strlen($signatureLine));
            }
        }

        $header = $rawLines[0];
        $trailer = end($rawLines);
        $bodyLines = array_slice($rawLines, 1, -1);

        $isHomologated = true;
        $homologationReason = null;

        // 1. Validação do Cabeçalho (Tipo 1) - exatamente 302 caracteres com CRC-16 Kermit
        if (strlen($header) !== 302) {
            $errors[] = sprintf('Cabeçalho (Tipo 1) possui %d caracteres; o leiaute oficial MTE (versão 004) exige exatamente 302.', strlen($header));
        } else {
            if (substr($header, 0, 9) !== '000000000') {
                $errors[] = 'NSR do Cabeçalho deve ser obrigatoriamente 000000000.';
            }
            if ($header[9] !== '1') {
                $errors[] = 'Tipo de registro do cabeçalho deve ser 1.';
            }
            if (! in_array($header[10], ['1', '2'], true)) {
                $errors[] = 'Identificador do empregador deve ser 1 (CNPJ) ou 2 (CPF).';
            }
            if (trim(substr($header, 11, 14)) === '') {
                $errors[] = 'CNPJ/CPF do empregador não pode ser vazio.';
            }

            if (trim(substr($header, 39, 150)) === '') {
                $errors[] = 'Razão social do empregador não pode ser vazia no cabeçalho.';
            }

            $inpiField = substr($header, 189, 17);
            if (trim($inpiField) === '') {
                $isHomologated = false;
                if (! $homologationReason) {
                    $homologationReason = 'pending_inpi';
                }
            }

            $dtInicio = substr($header, 206, 10);
            $dtFim = substr($header, 216, 10);
            $dtGeracao = substr($header, 226, 24);
            $versao = substr($header, 250, 3);
            $devType = substr($header, 253, 1);
            $devDoc = substr($header, 254, 14);
            $softwareModel = substr($header, 268, 30);

            if (! $this->isValidIsoDate($dtInicio)) {
                $errors[] = "Data inicial no cabeçalho inválida (esperado AAAA-MM-dd): {$dtInicio}";
            }
            if (! $this->isValidIsoDate($dtFim)) {
                $errors[] = "Data final no cabeçalho inválida (esperado AAAA-MM-dd): {$dtFim}";
            }
            if (! $this->isValidIsoDateTime($dtGeracao)) {
                $errors[] = "Data e hora de geração no cabeçalho inválida (esperado AAAA-MM-ddThh:mm:00ZZZZZ): {$dtGeracao}";
            }
            if ($versao !== '004') {
                $errors[] = "Versão do leiaute no cabeçalho deve ser 004 (encontrado: {$versao}).";
            }
            if (trim($devDoc) === '') {
                $isHomologated = false;
                if (! $homologationReason) {
                    $homologationReason = 'missing_developer_data';
                }
            }
            if (trim($softwareModel) !== '') {
                $errors[] = 'Campo reservado a modelo REP-C (posições 269-298) deve ser preenchido com espaços no caso de REP-P.';
            }

            $headerPrefix = substr($header, 0, 298);
            $expectedHeaderCrc = $this->fiscalHashService->calculateCrc16($headerPrefix);
            $actualHeaderCrc = substr($header, 298, 4);
            if (strtoupper($actualHeaderCrc) !== strtoupper($expectedHeaderCrc)) {
                $errors[] = "CRC-16 divergente no cabeçalho Tipo 1 (calculado: {$expectedHeaderCrc}, arquivo: {$actualHeaderCrc}).";
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
                case '2': // Identificação do Empregador - 331 caracteres com CRC-16
                    $countTipo2++;
                    if ($len !== 331) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 2 possui {$len} caracteres; o leiaute MTE exige exatamente 331.";
                    } else {
                        $dtGravacao = substr($line, 10, 24);
                        if (! $this->isValidIsoDateTime($dtGravacao)) {
                            $errors[] = "Linha {$lineNum}: Data/hora de gravação inválida no registro Tipo 2: {$dtGravacao}";
                        }
                        $cpfResp = substr($line, 34, 14);
                        if (trim($cpfResp) !== '' && ! ctype_digit(trim($cpfResp))) {
                            $errors[] = "Linha {$lineNum}: CPF do responsável deve conter apenas dígitos numéricos no registro Tipo 2: '{$cpfResp}'.";
                        }
                        $tpId = $line[48];
                        if (! in_array($tpId, ['1', '2'], true)) {
                            $errors[] = "Linha {$lineNum}: Tipo de identificador do empregador deve ser 1 ou 2 no registro Tipo 2: '{$tpId}'.";
                        }
                        $idEmp = substr($line, 49, 14);
                        if (trim($idEmp) === '') {
                            $errors[] = "Linha {$lineNum}: CNPJ ou CPF do empregador não pode ser vazio no registro Tipo 2.";
                        }
                        $razao = substr($line, 77, 150);
                        if (trim($razao) === '') {
                            $errors[] = "Linha {$lineNum}: Razão social do empregador não pode ser vazia no registro Tipo 2.";
                        }
                        $local = substr($line, 227, 100);
                        if (trim($local) === '') {
                            $errors[] = "Linha {$lineNum}: Local de prestação de serviços não pode ser vazio no registro Tipo 2.";
                        }
                        $prefix = substr($line, 0, 327);
                        $expectedCrc = $this->fiscalHashService->calculateCrc16($prefix);
                        $actualCrc = substr($line, 327, 4);
                        if (strtoupper($actualCrc) !== strtoupper($expectedCrc)) {
                            $errors[] = "Linha {$lineNum}: CRC-16 divergente no registro Tipo 2 (calculado: {$expectedCrc}, arquivo: {$actualCrc}).";
                        }
                    }
                    break;

                case '3': // Marcação REP-C/REP-A (Não aplicável ao REP-P)
                    $countTipo3++;
                    $errors[] = "Linha {$lineNum}: O modelo REP-P deve utilizar Registro Tipo 7 para marcações, nunca Tipo 3.";
                    break;

                case '4': // Ajuste do Relógio - 73 caracteres com CRC-16
                    $countTipo4++;
                    if ($len !== 73) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 4 possui {$len} caracteres; o leiaute MTE exige exatamente 73.";
                    } else {
                        $dtAntes = substr($line, 10, 24);
                        $dtDepois = substr($line, 34, 24);
                        if (! $this->isValidIsoDateTime($dtAntes)) {
                            $errors[] = "Linha {$lineNum}: Data/hora antes do ajuste inválida no registro Tipo 4: {$dtAntes}";
                        }
                        if (! $this->isValidIsoDateTime($dtDepois)) {
                            $errors[] = "Linha {$lineNum}: Data/hora ajustada inválida no registro Tipo 4: {$dtDepois}";
                        }
                        $cpfResp = substr($line, 58, 11);
                        if (! ctype_digit($cpfResp)) {
                            $errors[] = "Linha {$lineNum}: CPF do responsável deve conter 11 dígitos numéricos no registro Tipo 4: '{$cpfResp}'.";
                        }
                        $prefix = substr($line, 0, 69);
                        $expectedCrc = $this->fiscalHashService->calculateCrc16($prefix);
                        $actualCrc = substr($line, 69, 4);
                        if (strtoupper($actualCrc) !== strtoupper($expectedCrc)) {
                            $errors[] = "Linha {$lineNum}: CRC-16 divergente no registro Tipo 4 (calculado: {$expectedCrc}, arquivo: {$actualCrc}).";
                        }
                    }
                    break;

                case '5': // Empregado - 118 caracteres com CRC-16
                    $countTipo5++;
                    if ($len !== 118) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 5 possui {$len} caracteres; o leiaute MTE exige exatamente 118.";
                    } else {
                        $dtGravacao = substr($line, 10, 24);
                        if (! $this->isValidIsoDateTime($dtGravacao)) {
                            $errors[] = "Linha {$lineNum}: Data/hora de gravação inválida no registro Tipo 5: {$dtGravacao}";
                        }
                        $operacao = $line[34];
                        if (! in_array($operacao, ['I', 'A', 'E'], true)) {
                            $errors[] = "Linha {$lineNum}: Operação inválida no registro Tipo 5: '{$operacao}' (esperado I, A ou E).";
                        }
                        $cpfEmp = substr($line, 35, 12);
                        if (! ctype_digit($cpfEmp)) {
                            $errors[] = "Linha {$lineNum}: CPF do empregado deve conter 12 dígitos numéricos no registro Tipo 5: '{$cpfEmp}'.";
                        }
                        $nomeEmp = substr($line, 47, 52);
                        if (trim($nomeEmp) === '') {
                            $errors[] = "Linha {$lineNum}: Nome do empregado não pode ser vazio no registro Tipo 5.";
                        }
                        $cpfResp = substr($line, 103, 11);
                        if (! ctype_digit($cpfResp)) {
                            $errors[] = "Linha {$lineNum}: CPF do responsável deve conter 11 dígitos numéricos no registro Tipo 5: '{$cpfResp}'.";
                        }
                        $prefix = substr($line, 0, 114);
                        $expectedCrc = $this->fiscalHashService->calculateCrc16($prefix);
                        $actualCrc = substr($line, 114, 4);
                        if (strtoupper($actualCrc) !== strtoupper($expectedCrc)) {
                            $errors[] = "Linha {$lineNum}: CRC-16 divergente no registro Tipo 5 (calculado: {$expectedCrc}, arquivo: {$actualCrc}).";
                        }
                    }
                    break;

                case '6': // Eventos Sensíveis - 36 caracteres
                    $countTipo6++;
                    if ($len !== 36) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 6 possui {$len} caracteres; o leiaute MTE exige exatamente 36.";
                    } else {
                        $dtGravacao = substr($line, 10, 24);
                        if (! $this->isValidIsoDateTime($dtGravacao)) {
                            $errors[] = "Linha {$lineNum}: Data/hora de gravação inválida no registro Tipo 6: {$dtGravacao}";
                        }
                        $codEvento = substr($line, 34, 2);
                        if (! in_array($codEvento, ['02', '07', '08'], true)) {
                            $errors[] = "Linha {$lineNum}: Código de evento sensível inválido para REP-P: '{$codEvento}' (esperado 07 para disponibilidade ou 08 para indisponibilidade).";
                        }
                    }
                    break;

                case '7': // Marcação de Ponto REP-P - 137 caracteres
                    $countTipo7++;
                    if ($len !== 137) {
                        $errors[] = "Linha {$lineNum}: Registro Tipo 7 possui {$len} caracteres; o leiaute MTE exige exatamente 137.";
                    } else {
                        $dtMarc = substr($line, 10, 24);
                        $cpf = substr($line, 34, 12);
                        $dtGrav = substr($line, 46, 24);
                        $collector = substr($line, 70, 2);
                        $punchType = $line[72];
                        $hash = substr($line, 73, 64);

                        if (! $this->isValidIsoDateTime($dtMarc)) {
                            $errors[] = "Linha {$lineNum}: Data/hora da marcação inválida no registro Tipo 7: {$dtMarc}";
                        }
                        if (! ctype_digit($cpf)) {
                            $errors[] = "Linha {$lineNum}: CPF deve conter 12 dígitos numéricos no registro Tipo 7: {$cpf}";
                        }
                        if (! $this->isValidIsoDateTime($dtGrav)) {
                            $errors[] = "Linha {$lineNum}: Data/hora da gravação inválida no registro Tipo 7: {$dtGrav}";
                        }
                        if (! ctype_digit($collector)) {
                            $errors[] = "Linha {$lineNum}: Identificador do coletor deve ser numérico: {$collector}";
                        }
                        if (! in_array($punchType, ['0', '1'], true)) {
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

        // 3. Validação do Trailer (Tipo 9) - exatamente 64 caracteres
        if (strlen($trailer) !== 64) {
            $errors[] = sprintf('Trailer (Tipo 9) possui %d caracteres; o leiaute oficial MTE exige exatamente 64.', strlen($trailer));
        } else {
            if (substr($trailer, 0, 9) !== '999999999') {
                $errors[] = 'NSR do Trailer deve ser 999999999.';
            }
            if (substr($trailer, 63, 1) !== '9') {
                $errors[] = sprintf("Identificador final do trailer deve ser '9' na posição 64. Encontrado: '%s'.", substr($trailer, 63, 1));
            }

            $qtdTipo2Trailer = (int) substr($trailer, 9, 9);
            $qtdTipo3Trailer = (int) substr($trailer, 18, 9);
            $qtdTipo4Trailer = (int) substr($trailer, 27, 9);
            $qtdTipo5Trailer = (int) substr($trailer, 36, 9);
            $qtdTipo6Trailer = (int) substr($trailer, 45, 9);
            $qtdTipo7Trailer = (int) substr($trailer, 54, 9);

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
        }

        $totalRecords = $countTipo2 + $countTipo3 + $countTipo4 + $countTipo5 + $countTipo6 + $countTipo7;
        $structurallyValid = empty($errors);

        $hasInpi = strlen($header) === 302 && trim(substr($header, 189, 17)) !== '';
        $hasDevDoc = strlen($header) === 302 && trim(substr($header, 254, 14)) !== '';

        $signatureValid = $this->signatureVerifier->verify($content, $detachedSignature);
        $signatureStatus = $signatureValid ? 'signed' : 'pending_certificate';

        $isHomologated = $structurallyValid && $hasInpi && $hasDevDoc && $signatureValid;

        $homologationReason = null;
        if (! $isHomologated) {
            if (! $structurallyValid) {
                $homologationReason = 'structural_error';
            } elseif (! $hasInpi) {
                $homologationReason = 'pending_inpi';
            } elseif (! $hasDevDoc) {
                $homologationReason = 'missing_developer_data';
            } elseif (! $signatureValid) {
                $homologationReason = 'pending_certificate';
            }
        }

        return [
            'is_valid' => $structurallyValid,
            'structurally_valid' => $structurallyValid,
            'structureValid' => $structurallyValid,
            'signature_valid' => $signatureValid,
            'signatureValid' => $signatureValid,
            'signature_status' => $signatureStatus,
            'signatureStatus' => $signatureStatus,
            'is_homologated' => $isHomologated,
            'isHomologated' => $isHomologated,
            'reason' => $homologationReason,
            'homologation_reason' => $homologationReason,
            'homologationReason' => $homologationReason,
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
