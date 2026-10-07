<?php

namespace App\Domain\Compliance\Fiscal\Services;

use Carbon\Carbon;

class AuditChainHashService
{
    public const GENESIS_HASH = 'GENESIS_PONTOFACIL_2.0';

    /**
     * Calcula o hash interno de auditoria encadeado do PontoFácil.
     * Este hash pertence à camada interna de segurança e não se confunde
     * com o hash fiscal estrito exigido pelo leiaute MTE do AFD.
     */
    public function calculateAuditHash(
        int $establishmentId,
        ?int $userId,
        int $nsr,
        Carbon $occurredAtUtc,
        string $eventTypeOrDirection,
        ?string $previousAuditHash = null
    ): string {
        $prev = $previousAuditHash ?: self::GENESIS_HASH;

        $rawPayload = sprintf(
            '%d|%s|%d|%s|%s|%s',
            $establishmentId,
            (string) ($userId ?? 0),
            $nsr,
            $occurredAtUtc->toIso8601String(),
            $eventTypeOrDirection,
            $prev
        );

        return hash('sha256', $rawPayload);
    }
}
