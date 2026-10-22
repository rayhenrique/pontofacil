<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Models\ClosedPeriod;
use App\Models\MonthlyAttendanceReview;
use App\Models\Sector;
use App\Models\TreatmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelTreatmentEventAction
{
    /**
     * Cancela uma solicitação de tratamento pendente ou reverte administrativamente
     * uma justificativa de ausência / tratamento já aprovado.
     *
     * @param  TreatmentEvent  $event  Evento a ser cancelado ou revertido
     * @param  User  $actor  Usuário autenticado que solicitou o cancelamento/reversão
     * @param  string  $reason  Motivo obrigatório do cancelamento/reversão
     *
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function execute(
        TreatmentEvent $event,
        User $actor,
        string $reason
    ): TreatmentEvent {
        $trimmedReason = trim($reason);
        if (empty($trimmedReason) || mb_strlen($trimmedReason) < 5) {
            throw new \InvalidArgumentException('O motivo do cancelamento ou reversão é obrigatório (mínimo de 5 caracteres).');
        }

        return DB::transaction(function () use ($event, $actor, $trimmedReason) {
            /** @var TreatmentEvent $lockedEvent */
            $lockedEvent = TreatmentEvent::where('id', $event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedEvent->status === TreatmentEventStatus::Cancelled) {
                throw new \DomainException('Esta solicitação já foi cancelada anteriormente.');
            }

            if ($lockedEvent->status === TreatmentEventStatus::Revoked) {
                throw new \DomainException('Esta justificativa já foi revertida anteriormente.');
            }

            if ($lockedEvent->status === TreatmentEventStatus::Rejected) {
                throw new \DomainException('Solicitações rejeitadas não podem ser canceladas ou revertidas.');
            }

            if (! in_array($lockedEvent->status, [TreatmentEventStatus::Pending, TreatmentEventStatus::Approved], true)) {
                throw new \DomainException('Somente solicitações pendentes ou aprovadas podem ser canceladas ou revertidas.');
            }

            if (ClosedPeriod::isClosed($lockedEvent->effective_at->year, $lockedEvent->effective_at->month)) {
                throw new \DomainException(sprintf(
                    'Não é possível cancelar ou reverter tratamentos em competência fechada (%02d/%04d). Reabra a competência para efetuar alterações.',
                    $lockedEvent->effective_at->month,
                    $lockedEvent->effective_at->year
                ));
            }

            $lockedEvent->loadMissing(['employee.sector', 'employee.user']);

            $isPending = ($lockedEvent->status === TreatmentEventStatus::Pending);

            if ($isPending) {
                $isOwn = ($lockedEvent->requested_by === $actor->id) ||
                    ($lockedEvent->employee && $lockedEvent->employee->user_id === $actor->id);

                if ($actor->isAdmin()) {
                    // Admin pode cancelar qualquer pendência
                } elseif ($actor->isManager()) {
                    // Gestor pode cancelar a sua própria solicitação ou de colaboradores dos seus setores
                    if (! $isOwn) {
                        $managedSectorIds = Sector::where('manager_id', $actor->id)->pluck('id');
                        $empSectorId = $lockedEvent->employee?->sector_id;

                        if (! $empSectorId || ! $managedSectorIds->contains($empSectorId)) {
                            throw new \DomainException('O Gestor Imediato só possui autorização para cancelar solicitações de colaboradores dos seus setores de gestão.');
                        }
                    }
                } elseif ($actor->isEmployee()) {
                    // Colaborador só pode cancelar a sua própria solicitação pendente
                    if (! $isOwn) {
                        throw new \DomainException('O colaborador não possui permissão para cancelar solicitações de terceiros.');
                    }
                } else {
                    throw new \DomainException('Usuário sem autorização para cancelar solicitações de tratamento.');
                }

                $targetStatus = TreatmentEventStatus::Cancelled;
            } else {
                // Reversão de tratamento APROVADO
                if ($actor->isEmployee() && ! $actor->isManager() && ! $actor->isAdmin()) {
                    throw new \DomainException('Colaboradores não possuem permissão para reverter justificativas já aprovadas.');
                }

                if ($actor->isManager()) {
                    // 1. Segregação de Funções: Gestor não reverte a sua própria solicitação
                    $isOwn = ($lockedEvent->employee && $lockedEvent->employee->user_id === $actor->id);
                    if ($isOwn) {
                        throw new \DomainException('Segregação de Funções: Reversões do próprio Gestor devem ser realizadas exclusivamente pela Coordenação de RH.');
                    }

                    // 2. Delimitação Setorial
                    $managedSectorIds = Sector::where('manager_id', $actor->id)->pluck('id');
                    $empSectorId = $lockedEvent->employee?->sector_id;

                    if (! $empSectorId || ! $managedSectorIds->contains($empSectorId)) {
                        throw new \DomainException('O Gestor Imediato só possui autorização para reverter justificativas de colaboradores dos seus setores de gestão.');
                    }
                } elseif (! $actor->isAdmin()) {
                    throw new \DomainException('Usuário sem autorização para reverter justificativas aprovadas.');
                }

                $targetStatus = TreatmentEventStatus::Revoked;
            }

            $lockedEvent->update([
                'status' => $targetStatus,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $trimmedReason,
            ]);

            // Se o tratamento revertido estava aprovado e a competência estiver aberta, invalida a conferência anterior
            if (! $isPending) {
                $empId = $lockedEvent->employee_id ?? $lockedEvent->employment_id;
                if ($empId && ! ClosedPeriod::isClosed($lockedEvent->effective_at->year, $lockedEvent->effective_at->month)) {
                    MonthlyAttendanceReview::where('employee_id', $empId)
                        ->where('year', $lockedEvent->effective_at->year)
                        ->where('month', $lockedEvent->effective_at->month)
                        ->update([
                            'fingerprint' => null,
                            'has_unresolved_occurrences' => true,
                        ]);
                }
            }

            Log::info($isPending ? 'treatment.cancelled' : 'treatment.revoked', [
                'event_id' => $lockedEvent->id,
                'employee_id' => $lockedEvent->employee_id,
                'cancelled_by' => $actor->id,
                'status' => $targetStatus->value,
                'reason' => $trimmedReason,
            ]);

            return $lockedEvent;
        });
    }
}
