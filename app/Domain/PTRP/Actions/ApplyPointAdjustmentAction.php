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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplyPointAdjustmentAction
{
    public function __construct(
        protected RequestTreatmentEventAction $requestAction,
        protected ApproveTreatmentEventAction $approveAction,
        protected SectorScopeService $scopeService,
    ) {}

    /**
     * Aplica um ajuste unificado de ponto (inclusão de entrada/saída, desconsideração ou justificativa)
     * de forma atômica e com validação rigorosa de titularidade.
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
        ?string $idempotencyKey = null,
    ): TreatmentEvent {
        // Resolução de Timezone Institucional
        $timezone = $employee->sector?->establishment?->timezone
            ?? $employee->establishment?->timezone
            ?? config('app.timezone', 'America/Maceio');

        $carbonDate = is_string($date)
            ? Carbon::parse($date, $timezone)
            : $date->copy()->setTimezone($timezone);

        $dateStr = $carbonDate->toDateString();

        if (empty(trim($reason)) || mb_strlen(trim($reason)) < 5) {
            throw new \InvalidArgumentException('A justificativa do ajuste é obrigatória e deve conter pelo menos 5 caracteres.');
        }

        try {
            return DB::transaction(function () use (
                $employee,
                $actionType,
                $carbonDate,
                $dateStr,
                $timezone,
                $reason,
                $actor,
                $time,
                $referencePunchId,
                $reasonCategory,
                $attachmentPath,
                $autoApprove,
                $idempotencyKey
            ) {
                // Serialização de concorrência com lock pessimista no Employee
                Employee::where('id', $employee->id)->lockForUpdate()->first();

                // 1. Validação de Permissão e Segregação de Funções
                $this->validatePermissions($employee, $actor, $autoApprove);

                // 2. Validação de Competência Fechada (dentro da transação)
                if (ClosedPeriod::isClosed($carbonDate->year, $carbonDate->month)) {
                    throw new \DomainException(sprintf(
                        'A competência %02d/%04d encontra-se formalmente fechada e congelada fiscalmente. Ajustes retroativos não são permitidos.',
                        $carbonDate->month,
                        $carbonDate->year
                    ));
                }

                // 3. Idempotência / Prevenção de duplicidade por reenvio
                if ($idempotencyKey !== null) {
                    $existing = TreatmentEvent::where(function ($q) use ($employee) {
                        $q->where('employee_id', $employee->id)
                            ->orWhere('employment_id', $employee->id);
                    })
                        ->where('new_value_json->idempotency_key', $idempotencyKey)
                        ->first();

                    if ($existing) {
                        return $existing;
                    }
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

                        // Prevenção contra duplicidades e concorrência no mesmo minuto
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

                        $type = TreatmentEventType::PunchDisregarded;
                        $resolved = $this->resolveAndValidateDisregardedPunch(
                            $employee,
                            (string) $referencePunchId,
                            $dateStr,
                            $timezone,
                            $carbonDate
                        );

                        $referenceId = $resolved['referenceId'];
                        $effectiveAt = $resolved['effectiveAt'];
                        $newValueJson = $resolved['newValueJson'];
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

                if ($idempotencyKey !== null) {
                    $newValueJson['idempotency_key'] = $idempotencyKey;
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
            });
        } catch (\Throwable $e) {
            // Se ocorreu falha/rollback e um anexo foi gerado para esta tentativa, exclui para não deixar arquivo órfão
            if ($attachmentPath && Storage::disk('local')->exists($attachmentPath)) {
                Storage::disk('local')->delete($attachmentPath);
            }

            throw $e;
        }
    }

    /**
     * Valida de forma estrita e inequívoca a titularidade, data e integridade da marcação a ser desconsiderada.
     *
     * @return array{referenceId: string, effectiveAt: CarbonInterface, newValueJson: array}
     *
     * @throws AuthorizationException|\DomainException
     */
    protected function resolveAndValidateDisregardedPunch(
        Employee $employee,
        string $referencePunchId,
        string $dateStr,
        string $timezone,
        CarbonInterface $carbonDate,
    ): array {
        $rawRef = trim($referencePunchId);
        $isExplicitPunch = str_starts_with($rawRef, 'punch:');
        $isExplicitLegacy = str_starts_with($rawRef, 'legacy:') || str_starts_with($rawRef, 'entry:');

        $cleanId = $rawRef;
        if ($isExplicitPunch) {
            $cleanId = substr($rawRef, 6);
        } elseif ($isExplicitLegacy) {
            $cleanId = substr($rawRef, strpos($rawRef, ':') + 1);
        }

        // 1. Busca em PunchEvent
        $punchMatches = collect();
        if (! $isExplicitLegacy) {
            $punchMatches = PunchEvent::where('id', $cleanId)->get();
        }

        // 2. Busca em TimeEntry legado
        $legacyMatches = collect();
        if (! $isExplicitPunch && (ctype_digit((string) $cleanId) || is_numeric($cleanId))) {
            $legacyMatches = TimeEntry::where('id', (int) $cleanId)->get();
        }

        // 3. Verificação de Inexistência
        if ($punchMatches->isEmpty() && $legacyMatches->isEmpty()) {
            throw new \DomainException('A marcação informada para desconsideração não foi encontrada no sistema.');
        }

        // 4. Verificação de Titularidade (pertencimento exato ao Employee)
        $punchOwned = $punchMatches->filter(function (PunchEvent $p) use ($employee) {
            if ($p->employee_id !== null && (int) $p->employee_id === (int) $employee->id) {
                return true;
            }
            if ($p->user_id !== null && $employee->user_id !== null && (int) $p->user_id === (int) $employee->user_id) {
                return true;
            }

            return false;
        });

        $legacyOwned = $legacyMatches->filter(function (TimeEntry $entry) use ($employee) {
            return $employee->user_id !== null && (int) $entry->user_id === (int) $employee->user_id;
        });

        if ($punchOwned->isEmpty() && $legacyOwned->isEmpty()) {
            throw new AuthorizationException('A marcação informada pertence a outro colaborador e não pode ser manipulada.');
        }

        // 5. Verificação de Ambiguidade de IDs entre tabelas
        if ($punchOwned->isNotEmpty() && $legacyOwned->isNotEmpty()) {
            throw new \DomainException('Identificador de marcação ambíguo entre registro eletrônico e histórico legado. Especifique a origem inequívoca.');
        }

        // 6. Resolução dos dados do registro e validação de data no timezone institucional
        if ($punchOwned->isNotEmpty()) {
            /** @var PunchEvent $punch */
            $punch = $punchOwned->first();
            $punchDate = $punch->occurred_at_local
                ? $punch->occurred_at_local->copy()->setTimezone($timezone)->toDateString()
                : Carbon::parse($punch->occurred_at_utc, 'UTC')->setTimezone($timezone)->toDateString();

            if ($punchDate !== $dateStr) {
                throw new \DomainException(sprintf(
                    'A marcação selecionada (%s) pertence à data %s e não corresponde à data do ajuste solicitado (%s).',
                    $punch->occurred_at_local?->format('H:i') ?? '',
                    $punchDate,
                    $dateStr
                ));
            }

            $targetRefId = (string) $punch->id;
            $effectiveAt = $punch->occurred_at_local ? $punch->occurred_at_local->copy()->setTimezone($timezone) : $carbonDate;
            $newValueJson = [
                'original_nsr' => $punch->nsr,
                'original_time' => $punch->occurred_at_local?->format('H:i:s'),
                'direction' => $punch->direction,
                'source_type' => 'punch_event',
            ];
        } else {
            /** @var TimeEntry $legacy */
            $legacy = $legacyOwned->first();
            $legacyDate = Carbon::parse($legacy->timestamp)->setTimezone($timezone)->toDateString();

            if ($legacyDate !== $dateStr) {
                throw new \DomainException(sprintf(
                    'O registro histórico selecionado (%s) pertence à data %s e não corresponde à data do ajuste solicitado (%s).',
                    Carbon::parse($legacy->timestamp)->format('H:i'),
                    $legacyDate,
                    $dateStr
                ));
            }

            $targetRefId = (string) $legacy->id;
            $effectiveAt = Carbon::parse($legacy->timestamp, $timezone);
            $newValueJson = [
                'legacy_entry_id' => $legacy->id,
                'original_time' => Carbon::parse($legacy->timestamp)->format('H:i:s'),
                'direction' => match ($legacy->type) {
                    'clock_out', 'out' => 'out',
                    default => 'in',
                },
                'source_type' => 'time_entry',
            ];
        }

        // 7. Rejeição de desconsideração duplicada ou conflitante
        $existingTreatment = TreatmentEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->where('reference_punch_id', $targetRefId)
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->whereIn('status', [TreatmentEventStatus::Pending, TreatmentEventStatus::Approved])
            ->first();

        if ($existingTreatment) {
            $statusMsg = $existingTreatment->status === TreatmentEventStatus::Approved
                ? 'já foi formalmente desconsiderada'
                : 'já possui uma solicitação de desconsideração pendente de análise';

            throw new \DomainException("Esta marcação {$statusMsg}.");
        }

        return [
            'referenceId' => $targetRefId,
            'effectiveAt' => $effectiveAt,
            'newValueJson' => $newValueJson,
        ];
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
