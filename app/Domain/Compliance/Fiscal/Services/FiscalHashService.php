<?php

namespace App\Domain\Compliance\Fiscal\Services;

use Carbon\Carbon;

class FiscalHashService
{
    /**
     * Calcula o hash fiscal estrito exigido pelo leiaute oficial MTE (Portaria 671/2021)
     * para o Registro Tipo 3 (Marcação de Ponto REP-P).
     *
     * A cadeia canônica oficial é formada por exatamente 37 caracteres contíguos:
     * - NSR: 9 posições numéricas (pos 01 a 09)
     * - Tipo de Registro: '3' (pos 10)
     * - Data da Marcação: DDMMAAAA (pos 11 a 18)
     * - Horário da Marcação: HHMM (pos 19 a 22)
     * - Fuso Horário: 4 posições, ex: '-030' (pos 23 a 26)
     * - CPF do Trabalhador: 11 dígitos numéricos (pos 27 a 37)
     *
     * O Hash SHA-256 gerado ocupa as posições 38 a 101 da linha do AFD.
     * Não utiliza encadeamento com registros anteriores no hash fiscal oficial.
     */
    public function calculatePunchFiscalHash(
        int $nsr,
        Carbon $occurredAtLocal,
        string $utcOffset,
        ?string $cpf
    ): string {
        $canonical = $this->canonicalPunchFiscalString($nsr, $occurredAtLocal, $utcOffset, $cpf);

        return hash('sha256', $canonical);
    }

    /**
     * Retorna a string canônica oficial de 37 caracteres utilizada para a geração do hash fiscal.
     */
    public function canonicalPunchFiscalString(
        int $nsr,
        Carbon $occurredAtLocal,
        string $utcOffset,
        ?string $cpf
    ): string {
        $nsrFormatted = str_pad((string) $nsr, 9, '0', STR_PAD_LEFT);
        $tipoRegistro = '3';
        $dtMarcacao = $occurredAtLocal->format('dmY');
        $hrMarcacao = $occurredAtLocal->format('Hi');

        // Formatação do fuso horário em exatamente 4 caracteres (ex: -030) compatível com AFD MTE 2026
        $offsetClean = preg_replace('/[^0-9+-]/', '', $utcOffset ?: '-0300');
        if (strlen($offsetClean) === 3) {
            $offsetClean .= '00';
        }
        $offset = str_pad(substr($offsetClean, 0, 5), 4, '0', STR_PAD_RIGHT);
        if (strlen($offset) > 4) {
            $offset = substr($offset, 0, 4);
        }

        // CPF com 11 dígitos numéricos
        $cpfClean = preg_replace('/\D/', '', (string) $cpf);
        $cpfFormatted = str_pad(substr($cpfClean, 0, 11), 11, '0', STR_PAD_LEFT);

        return $nsrFormatted.$tipoRegistro.$dtMarcacao.$hrMarcacao.$offset.$cpfFormatted;
    }

    /**
     * Calcula o hash fiscal para outros tipos de registros fiscais da ARP.
     */
    public function calculateGenericArpFiscalHash(
        int $nsr,
        string $eventType,
        Carbon $occurredAtLocal,
        string $utcOffset,
        string $identifier
    ): string {
        $nsrFormatted = str_pad((string) $nsr, 9, '0', STR_PAD_LEFT);
        $dt = $occurredAtLocal->format('dmYHi');
        $canonical = sprintf('%s|%s|%s|%s|%s', $nsrFormatted, $eventType, $dt, $utcOffset, $identifier);

        return hash('sha256', $canonical);
    }
}
