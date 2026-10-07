<?php

namespace App\Domain\Compliance\Fiscal\Services;

use Carbon\Carbon;

class FiscalHashService
{
    /**
     * Calcula o hash fiscal oficial do REP-P (Registro Tipo 7) conforme o leiaute do MTE (Portaria 671/2021 Anexo V).
     *
     * A cadeia canônica para o hash é formada por 137 caracteres:
     * - Campos 1 a 7 (73 caracteres):
     *   1. NSR (001-009): 9 posições numéricas
     *   2. Tipo do registro (010-010): '7' (1 posição)
     *   3. Data e hora da marcação (011-034): 24 posições (AAAA-MM-ddThh:mm:00ZZZZZ)
     *   4. CPF do trabalhador (035-046): 12 posições numéricas (com zero à esquerda)
     *   5. Data e hora da gravação (047-070): 24 posições (AAAA-MM-ddThh:mm:00ZZZZZ)
     *   6. Identificador do coletor (071-072): 2 posições numéricas (ex: '01' mobile, '02' browser)
     *   7. Tipo de marcação (073-073): 1 posição numérica ('0' online, '1' offline)
     * - Hash do Registro Tipo 7 imediatamente anterior (ou 64 zeros se for o primeiro) (64 caracteres)
     *
     * O Hash SHA-256 é calculado sobre os 137 caracteres e preenche as posições 074 a 137 da linha do AFD.
     */
    public function calculateTipo7FiscalHash(
        int $nsr,
        Carbon $occurredAtLocal,
        Carbon $recordedAtLocal,
        ?string $cpf,
        string $collectorType = '02',
        string $punchType = '0',
        ?string $previousTipo7FiscalHash = null
    ): string {
        $prefix = $this->canonicalTipo7Prefix(
            $nsr,
            $occurredAtLocal,
            $recordedAtLocal,
            $cpf,
            $collectorType,
            $punchType
        );

        // Regra Oficial Portaria 671 MTE:
        // O código hash do registro é gerado a partir dos campos do registro,
        // acrescido do código hash do registro anterior, quando existir.
        // Se for o primeiro registro da cadeia (gênese), o hash anterior não existe e não se acrescentam zeros.
        $previousHash = '';
        if ($previousTipo7FiscalHash !== null && trim($previousTipo7FiscalHash) !== '') {
            $cleaned = strtolower(substr(trim($previousTipo7FiscalHash), 0, 64));
            $previousHash = str_pad($cleaned, 64, '0', STR_PAD_LEFT);
        }

        $canonicalToHash = $prefix.$previousHash;

        return hash('sha256', $canonicalToHash);
    }

    /**
     * Retorna o prefixo canônico de 73 caracteres dos campos 1 a 7 do Registro Tipo 7.
     */
    public function canonicalTipo7Prefix(
        int $nsr,
        Carbon $occurredAtLocal,
        Carbon $recordedAtLocal,
        ?string $cpf,
        string $collectorType = '02',
        string $punchType = '0'
    ): string {
        $nsrFormatted = str_pad((string) $nsr, 9, '0', STR_PAD_LEFT);
        $tipo = '7';
        $occurredIso = $this->formatDateTimeIso($occurredAtLocal);

        $cpfClean = preg_replace('/\D/', '', (string) $cpf);
        $cpfFormatted = str_pad(substr($cpfClean, 0, 11), 12, '0', STR_PAD_LEFT);

        $recordedIso = $this->formatDateTimeIso($recordedAtLocal);
        $collectorFormatted = str_pad(substr(preg_replace('/\D/', '', $collectorType) ?: '02', 0, 2), 2, '0', STR_PAD_LEFT);
        $punchTypeFormatted = in_array($punchType, ['0', '1'], true) ? $punchType : '0';

        return $nsrFormatted.$tipo.$occurredIso.$cpfFormatted.$recordedIso.$collectorFormatted.$punchTypeFormatted;
    }

    /**
     * Formata data e hora no padrão exigido pela Portaria 671 MTE: AAAA-MM-ddThh:mm:00ZZZZZ (24 caracteres).
     */
    public function formatDateTimeIso(Carbon $dt, ?string $utcOffset = null): string
    {
        $offset = $utcOffset ?: $dt->format('P');
        $cleanOffset = preg_replace('/[^0-9+-]/', '', $offset);
        if (strlen($cleanOffset) === 3) {
            $cleanOffset .= '00';
        }
        $cleanOffset = str_pad(substr($cleanOffset, 0, 5), 5, '0', STR_PAD_RIGHT);

        return $dt->format('Y-m-d\TH:i:00').$cleanOffset;
    }

    /**
     * Calcula o CRC-16 padrão CCITT-TRUE / KERMIT oficial do MTE (Portaria 671/2021).
     * Teste oficial de validação: para "123456789", o valor é 0x2189.
     */
    public function calculateCrc16(string $content): string
    {
        $crc = 0;
        $len = strlen($content);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($content[$i]);
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 1) ? (($crc >> 1) ^ 0x8408) : ($crc >> 1);
            }
        }

        return sprintf('%04X', $crc);
    }

    /**
     * Método de compatibilidade para cálculo de hash de batida.
     * Utiliza o cálculo oficial do Registro Tipo 7 do REP-P.
     */
    public function calculatePunchFiscalHash(
        int $nsr,
        Carbon $occurredAtLocal,
        string $utcOffset,
        ?string $cpf,
        ?string $previousTipo7FiscalHash = null
    ): string {
        return $this->calculateTipo7FiscalHash(
            nsr: $nsr,
            occurredAtLocal: $occurredAtLocal,
            recordedAtLocal: $occurredAtLocal,
            cpf: $cpf,
            collectorType: '02',
            punchType: '0',
            previousTipo7FiscalHash: $previousTipo7FiscalHash
        );
    }

    /**
     * Calcula o hash fiscal genérico para outros tipos de eventos fiscais da ARP.
     */
    public function calculateGenericArpFiscalHash(
        int $nsr,
        string $eventType,
        Carbon $occurredAtLocal,
        string $utcOffset,
        string $identifier
    ): string {
        $nsrFormatted = str_pad((string) $nsr, 9, '0', STR_PAD_LEFT);
        $dt = $this->formatDateTimeIso($occurredAtLocal, $utcOffset);
        $canonical = sprintf('%s|%s|%s|%s', $nsrFormatted, $eventType, $dt, $identifier);

        return hash('sha256', $canonical);
    }
}
