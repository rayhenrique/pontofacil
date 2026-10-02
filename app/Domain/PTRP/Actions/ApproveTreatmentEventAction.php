<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Models\ClosedPeriod;
use App\Models\Sector;
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

        $event->loadMissing(['employee.sector', 'employee.user']);

        // Se o aprovador for Gestor Imediato (Manager)
        if ($approvedBy->isManager()) {
            // 1. Segregação de Funções: O gestor NÃO PODE aprovar solicitações dele próprio.
            // Somente a Coordenação de RH (Admin) pode aprovar solicitações do Gestor.
            if ($event->requested_by === $approvedBy->id || ($event->employee && $event->employee->user_id === $approvedBy->id)) {
                throw new \DomainException('Segregação de Funções: Solicitações do próprio Gestor devem ser aprovadas exclusivamente pela Coordenação de RH.');
            }

            // 2. Delimitação Territorial/Setorial: Gestor só aprova colaboradores dos seus setores gerenciados
            $managedSectorIds = Sector::where('manager_id', $approvedBy->id)->pluck('id');
            $employeeSectorId = $event->employee?->sector_id;

            if (! $employeeSectorId || ! $managedSectorIds->contains($employeeSectorId)) {
                throw new \DomainException('O Gestor Imediato só possui autorização para aprovar solicitações de colaboradores dos seus setores de gestão.');
            }
        } elseif (! $approvedBy->isAdmin()) {
            // Se for Employee comum ou Auditor tentando aprovar
            throw new \DomainException('O colaborador não possui permissão para aprovar a própria solicitação.');
        }
        // Se for Admin (Coordenação de RH): Poder Total! O Admin pode aprovar qualquer solicitação (visão global, ausência do gestor, férias, solicitações do próprio gestor).

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
