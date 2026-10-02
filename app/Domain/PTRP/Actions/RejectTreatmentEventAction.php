<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Models\TreatmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RejectTreatmentEventAction
{
    public function execute(
        TreatmentEvent $event,
        User $rejectedBy,
        string $rejectionReason,
    ): TreatmentEvent {
        if ($event->status !== TreatmentEventStatus::Pending) {
            throw new \DomainException('Somente solicitações pendentes podem ser rejeitadas.');
        }

        if (empty(trim($rejectionReason))) {
            throw new \InvalidArgumentException('O motivo da recusa é estritamente obrigatório.');
        }

        $event->update([
            'status' => TreatmentEventStatus::Rejected,
            'rejected_by' => $rejectedBy->id,
            'rejection_reason' => trim($rejectionReason),
            'decided_at' => now(),
        ]);

        Log::info('treatment.rejected', [
            'event_id' => $event->id,
            'employee_id' => $event->employee_id,
            'rejected_by' => $rejectedBy->id,
            'rejection_reason' => $rejectionReason,
        ]);

        return $event;
    }
}
