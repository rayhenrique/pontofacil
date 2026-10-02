<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Models\Sector;
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

        $event->loadMissing(['employee.sector', 'employee.user']);

        // Se o usuário for Gestor Imediato (Manager)
        if ($rejectedBy->isManager()) {
            // 1. Segregação de Funções: O gestor NÃO PODE recusar/decidir solicitações dele próprio.
            // Somente a Coordenação de RH (Admin) decide solicitações do Gestor.
            if ($event->requested_by === $rejectedBy->id || ($event->employee && $event->employee->user_id === $rejectedBy->id)) {
                throw new \DomainException('Segregação de Funções: Solicitações do próprio Gestor devem ser decididas exclusivamente pela Coordenação de RH.');
            }

            // 2. Delimitação Territorial/Setorial: Gestor só decide sobre colaboradores dos seus setores
            $managedSectorIds = Sector::where('manager_id', $rejectedBy->id)->pluck('id');
            $employeeSectorId = $event->employee?->sector_id;

            if (! $employeeSectorId || ! $managedSectorIds->contains($employeeSectorId)) {
                throw new \DomainException('O Gestor Imediato só possui autorização para recusar solicitações de colaboradores dos seus setores de gestão.');
            }
        } elseif (! $rejectedBy->isAdmin()) {
            // Se for Employee comum ou Auditor tentando recusar
            throw new \DomainException('O colaborador não possui permissão para recusar solicitações de tratamento.');
        }
        // Se for Admin: Poder Total! O Admin (Coordenação de RH) tem visão global e pode recusar qualquer solicitação.

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
