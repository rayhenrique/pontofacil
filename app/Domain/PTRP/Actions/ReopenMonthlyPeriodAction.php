<?php

namespace App\Domain\PTRP\Actions;

use App\Models\ClosedPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReopenMonthlyPeriodAction
{
    /**
     * Executa a reabertura formal de uma competência fechada.
     * Exige justificativa formal obrigatória do Admin/RH.
     * Preserva integralmente os snapshots históricos anteriores para fins de auditoria.
     */
    public function execute(
        int $year,
        int $month,
        User $reopenedBy,
        string $reason,
    ): ClosedPeriod {
        if (! $reopenedBy->isAdmin()) {
            throw new \DomainException('Somente usuários com perfil de Administrador/RH podem reabrir competências.');
        }

        $trimmedReason = trim($reason);
        if (mb_strlen($trimmedReason) < 10) {
            throw new \InvalidArgumentException('A justificativa de reabertura é estritamente obrigatória e deve conter no mínimo 10 caracteres.');
        }

        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);

        if (! $closedPeriod) {
            throw new \DomainException(sprintf('A competência %02d/%04d não possui registro de fechamento anterior.', $month, $year));
        }

        if ($closedPeriod->status === 'reopened') {
            throw new \DomainException(sprintf('A competência %02d/%04d já se encontra em estado de reabertura.', $month, $year));
        }

        $closedPeriod->update([
            'status' => 'reopened',
            'reopened_by' => $reopenedBy->id,
            'reopened_at' => now(),
            'reopen_reason' => $trimmedReason,
        ]);

        Log::warning('period.reopened', [
            'period_id' => $closedPeriod->id,
            'year' => $year,
            'month' => $month,
            'reopened_by' => $reopenedBy->id,
            'reason' => $trimmedReason,
            'previous_version' => $closedPeriod->snapshot_version,
            'previous_hash' => $closedPeriod->snapshot_hash,
        ]);

        return $closedPeriod;
    }
}
