<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\TreatmentEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class RequestTreatmentEventAction
{
    public function execute(
        Employee $employee,
        TreatmentEventType $type,
        CarbonInterface $effectiveAt,
        string $reasonText,
        User $requestedBy,
        ?string $referencePunchId = null,
        ?array $newValueJson = null,
        ?string $attachmentPath = null,
    ): TreatmentEvent {
        if (empty(trim($reasonText))) {
            throw new \InvalidArgumentException('A justificativa da solicitação de tratamento é obrigatória.');
        }

        if (ClosedPeriod::isClosed($effectiveAt->year, $effectiveAt->month)) {
            throw new \DomainException(sprintf(
                'A competência %02d/%04d encontra-se fechada e congelada. Solicitações de tratamento retroativas não são permitidas.',
                $effectiveAt->month,
                $effectiveAt->year
            ));
        }

        $event = TreatmentEvent::create([
            'employee_id' => $employee->id,
            'employment_id' => $employee->id,
            'reference_punch_id' => $referencePunchId,
            'type' => $type,
            'status' => TreatmentEventStatus::Pending,
            'effective_at' => $effectiveAt,
            'new_value_json' => $newValueJson,
            'reason_text' => trim($reasonText),
            'attachment_path' => $attachmentPath,
            'requested_by' => $requestedBy->id,
        ]);

        Log::info('treatment.requested', [
            'event_id' => $event->id,
            'employee_id' => $employee->id,
            'type' => $type->value,
            'requested_by' => $requestedBy->id,
            'effective_at' => $effectiveAt->toDateTimeString(),
        ]);

        return $event;
    }
}
