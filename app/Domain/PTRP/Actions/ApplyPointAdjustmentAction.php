<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\Company\Services\SectorScopeService;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\PunchEvent;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;

class ApplyPointAdjustmentAction
{
    public function __construct(
        protected RequestTreatmentEventAction $requestAction,
        protected ApproveTreatmentEventAction $approveAction,
        protected SectorScopeService $scopeService,
    ) {}

    /**
     * Aplica um ajuste unificado de ponto (inclusão de entrada/saída, desconsideração ou justificativa).
     *
     * @throws AuthorizationException|\DomainException|\InvalidArgumentException
     */
    public function execute(
        Employee $employee,
        string $actionType, // 'add_entry', 'add_exit', 'disregard_punch', 'justify_absence'
        CarbonInterface|string $date,
        string $reason,
        User $actor,
        ?string $time = null,
        ?string $referencePunchId = null,
        ?string $reasonCategory = null,
        ?string $attachmentPath = null,
        bool $autoApprove = false,
    ): TreatmentEvent {
        // 1. Validação de Permissão e Segregação de Funções
        $this->validatePermissions($employee, $actor, $autoApprove);

        // 2. Resolução de Timezone Institucional
        $timezone = $employee->sector?->establishment?->timezone
            ?? $employee->establishment?->timezone
            ?? config('app.timezone', 'America/Maceio');

        $carbonDate = is_string($date)
            ? Carbon::parse($date, $timezone)
            : $date->copy()->setTimezone($timezone);

        $dateStr = $carbonDate->toDateString();

        // 3. Validação de Competência Fechada
        if (ClosedPeriod::isClosed($carbonDate->year, $carbonDate->month)) {
            throw new \DomainException(sprintf(
                'A competência %02d/%04d encontra-se formalmente fechada e congelada fiscalmente. Ajustes retroativos não são permitidos.',
                $carbonDate->month,
                $carbonDate->year
            ));
        }

        if (empty(trim($reason)) || mb_strlen(trim($reason)) < 5) {
            throw new \InvalidArgumentException('A justificativa do ajuste é obrigatória e deve conter pelo menos 5 caracteres.');
        }

        // 4. Mapeamento por Tipo de Ação
        $referenceId = null;
        $reasonCode = null;
        $newValueJson = null;

        switch ($actionType) {
            case 'add_entry':
            case 'add_exit':
                if (empty($time)) {
                    throw new \InvalidArgumentException('O horário da batida manual é estritamente obrigatório.');
                }

                $direction = $actionType === 'add_entry' ? 'in' : 'out';
                $effectiveAt = Carbon::parse("{$dateStr} {$time}", $timezone);
                $type = TreatmentEventType::ManualPunchAdded;

                // Prevenção contra duplicidades no mesmo minuto
                $this->assertNoPunchConflict($employee, $effectiveAt, $direction);

                $newValueJson = [
                    'direction' => $direction,
                    'suggested_time' => $effectiveAt->format('H:i'),
                    'effective_date' => $dateStr,
                    'is_manual' => true,
                ];
                break;

            case 'disregard_punch':
                if (empty($referencePunchId)) {
                    throw new \InvalidArgumentException('É obrigatório selecionar a marcação que será desconsiderada.');
                }

                $referenceId = (string) $referencePunchId;
                $type = TreatmentEventType::PunchDisregarded;

                $punch = PunchEvent::find($referenceId);
                if ($punch) {
                    $effectiveAt = $punch->occurred_at_local ? $punch->occurred_at_local->copy()->setTimezone($timezone) : $carbonDate;
                    $newValueJson = [
                        'original_nsr' => $punch->nsr,
                        'original_time' => $punch->occurred_at_local?->format('H:i:s'),
                        'direction' => $punch->direction,
                    ];
                } else {
                    $legacy = TimeEntry::find($referenceId);
                    if ($legacy) {
                        $effectiveAt = Carbon::parse($legacy->timestamp, $timezone);
                        $newValueJson = [
                            'legacy_entry_id' => $legacy->id,
                            'original_time' => Carbon::parse($legacy->timestamp)->format('H:i:s'),
                            'direction' => $legacy->type === 'in' ? 'in' : 'out',
                        ];
                    } else {
                        $effectiveAt = $carbonDate;
                    }
                }
                break;

            case 'justify_absence':
                $type = TreatmentEventType::AbsenceJustified;
                $effectiveAt = $carbonDate->copy()->startOfDay();
                $reasonCode = $reasonCategory ?: 'other';

                $newValueJson = [
                    'reason_category' => $reasonCode,
                    'period_type' => 'full_day',
                ];
                break;

            default:
                throw new \InvalidArgumentException("Tipo de ação de ajuste desconhecido: {$actionType}");
        }

        // 5. Criação do Evento de Tratamento (sempre com requested_by = $actor)
        $event = $this->requestAction->execute(
            employee: $employee,
            type: $type,
            effectiveAt: $effectiveAt,
            reasonText: trim($reason),
            requestedBy: $actor,
            referencePunchId: $referenceId,
            newValueJson: $newValueJson,
            attachmentPath: $attachmentPath,
            reasonCode: $reasonCode,
        );

        // 6. Aprovação Imediata caso solicitada por usuário com autorização
        if ($autoApprove) {
            $isOwnRequest = ($employee->user_id === $actor->id);

            // Segregação de Funções: Gestor não aprova pedido próprio
            if ($actor->isManager() && $isOwnRequest) {
                throw new \DomainException('Segregação de Funções: Solicitações do próprio Gestor devem ser aprovadas exclusivamente pela Coordenação de RH.');
            }

            if ($actor->isAdmin() || ($actor->isManager() && ! $isOwnRequest)) {
                $event = $this->approveAction->execute(
                    event: $event,
                    approvedBy: $actor,
                    note: 'Ajuste manual de ponto aprovado diretamente pela gestão/RH com auditoria.',
                );
            }
        }

        return $event;
    }

    /**
     * Valida as permissões do usuário que está operando o ajuste.
     */
    protected function validatePermissions(Employee $employee, User $actor, bool $autoApprove): void
    {
        if ($actor->isAdmin()) {
            return;
        }

        if ($actor->isManager()) {
            // Gestor operando colaborador de outro setor é bloqueado
            $this->scopeService->authorizeEmployee($actor, $employee);

            // Se for ele próprio, não pode auto-aprovar
            if ($employee->user_id === $actor->id && $autoApprove) {
                throw new \DomainException('Segregação de Funções: O gestor não pode aprovar ajustes em seu próprio espelho de ponto.');
            }

            return;
        }

        // Colaborador comum só pode solicitar para si mesmo e nunca auto-aprovar
        if ($employee->user_id !== $actor->id) {
            throw new AuthorizationException('Você não possui permissão para solicitar ajustes para outro colaborador.');
        }

        if ($autoApprove) {
            throw new AuthorizationException('Colaboradores não possuem permissão para auto-aprovar solicitações de ajuste.');
        }
    }

    /**
     * Prevenção contra conflitos de batidas no mesmo horário e direção.
     */
    protected function assertNoPunchConflict(Employee $employee, CarbonInterface $time, string $direction): void
    {
        $dayStart = $time->copy()->subMinute();
        $dayEnd = $time->copy()->addMinute();

        // 1. Marcação eletrônica bruta original no mesmo horário
        $punchConflict = PunchEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('user_id', $employee->user_id);
        })
            ->whereBetween('occurred_at_local', [$dayStart, $dayEnd])
            ->where('direction', $direction)
            ->exists();

        if ($punchConflict) {
            throw new \DomainException(sprintf(
                'Já existe uma marcação eletrônica de %s registrada às %s.',
                $direction === 'in' ? 'entrada' : 'saída',
                $time->format('H:i')
            ));
        }

        // 2. Batida manual já aprovada no mesmo horário
        $treatmentConflict = TreatmentEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->where('type', TreatmentEventType::ManualPunchAdded)
            ->where('status', TreatmentEventStatus::Approved)
            ->whereBetween('effective_at', [$dayStart, $dayEnd])
            ->first();

        if ($treatmentConflict) {
            $conflictDir = $treatmentConflict->new_value_json['direction'] ?? null;
            if ($conflictDir === $direction) {
                throw new \DomainException(sprintf(
                    'Já existe uma batida manual de %s aprovada às %s.',
                    $direction === 'in' ? 'entrada' : 'saída',
                    $time->format('H:i')
                ));
            }
        }
    }
}
