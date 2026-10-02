<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Models\ClosedPeriod;
use App\Models\TreatmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ApproveTreatmentEventAction
{
    public function execute(
        TreatmentEvent $event,
        User $approvedBy,
        ?string $note = null,
    ): TreatmentEvent {
        if ($event->status !== TreatmentEventStatus::Pending) {
            throw new \DomainException('Somente solicitações pendentes podem ser aprovadas.');
        }

        if (ClosedPeriod::isClosed($event->effective_at->year, $event->effective_at->month)) {
            throw new \DomainException(sprintf(
                'Não é possível aprovar tratamentos em competência fechada (%02d/%04d). Reabra a competência para efetuar alterações.',
                $event->effective_at->month,
                $event->effective_at->year
            ));
        }

        // Separação de funções: funcionário comum não pode aprovar a própria solicitação
        if ($event->requested_by === $approvedBy->id && ! $approvedBy->isAdmin()) {
            throw new \DomainException('O colaborador não possui permissão para aprovar a própria solicitação.');
        }

        $event->update([
            'status' => TreatmentEventStatus::Approved,
            'approved_by' => $approvedBy->id,
            'decided_at' => now(),
        ]);

        Log::info('treatment.approved', [
            'event_id' => $event->id,
            'employee_id' => $event->employee_id,
            'approved_by' => $approvedBy->id,
            'note' => $note,
        ]);

        return $event;
    }
}
