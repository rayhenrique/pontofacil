<?php

namespace App\Domain\Settlement\Services;

use App\Domain\PTRP\Actions\RecordTimeBankTransactionAction;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\Settlement\Enums\SettlementDischargeType;
use App\Domain\Settlement\Enums\SettlementOriginType;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementType;
use App\Models\Employee;
use App\Models\Sector;
use App\Models\TimeBankAccount;
use App\Models\User;
use App\Models\WorkTimeSettlement;
use App\Models\WorkTimeSettlementDischarge;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkTimeSettlementManager
{
    /**
     * Cria uma nova operação de destinação/liquidação com controle estrito de concorrência,
     * idempotência e resolução autoritativa de saldos no servidor.
     */
    public function createSettlement(array $data, User $actor): WorkTimeSettlement
    {
        return DB::transaction(function () use ($data, $actor) {
            // 1. Idempotência com validação de payload idêntico
            if (! empty($data['idempotency_key'])) {
                $existing = WorkTimeSettlement::where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $expectedMinutes = (int) ($data['minutes'] ?? 0);
                    $expectedEmployeeId = (int) $data['employee_id'];
                    $expectedPeriod = (string) $data['reference_period'];
                    $expectedType = $data['settlement_type'] instanceof WorkTimeSettlementType
                        ? $data['settlement_type']->value
                        : (string) $data['settlement_type'];

                    $isMatch = ((int) $existing->employee_id === $expectedEmployeeId)
                        && ((int) $existing->minutes === $expectedMinutes)
                        && ((string) $existing->reference_period === $expectedPeriod)
                        && ($existing->settlement_type->value === $expectedType);

                    if (! $isMatch) {
                        throw new DomainException('Conflito de idempotência: a chave informada já foi utilizada para uma operação com parâmetros diferentes.');
                    }

                    return $existing;
                }
            }

            $minutes = (int) ($data['minutes'] ?? 0);
            if ($minutes === 0) {
                throw new DomainException('A quantidade de minutos da destinação não pode ser zero.');
            }

            $employeeId = (int) $data['employee_id'];
            $employee = Employee::where('id', $employeeId)->lockForUpdate()->firstOrFail();

            $initialStatus = isset($data['status'])
                ? ($data['status'] instanceof WorkTimeSettlementStatus ? $data['status'] : WorkTimeSettlementStatus::from($data['status']))
                : WorkTimeSettlementStatus::Pending;

            // 2. Autorização rigorosa no servidor
            $this->authorizeOperation($actor, $employee, 'create', $initialStatus);

            $referencePeriod = (string) $data['reference_period'];
            $originType = $data['origin_type'] instanceof SettlementOriginType
                ? $data['origin_type']
                : SettlementOriginType::from($data['origin_type']);

            $settlementType = $data['settlement_type'] instanceof WorkTimeSettlementType
                ? $data['settlement_type']
                : WorkTimeSettlementType::from($data['settlement_type']);

            // 3. Validação de compatibilidade de natureza (crédito vs débito)
            if ($minutes > 0 && in_array($settlementType, [WorkTimeSettlementType::DeficitCompensation, WorkTimeSettlementType::PayrollDeduction], true)) {
                throw new DomainException(sprintf(
                    'Natureza incompatível: a operação "%s" é de débito, mas a quantidade de minutos é positiva (+%d min).',
                    $settlementType->label(),
                    $minutes
                ));
            }

            if ($minutes < 0 && in_array($settlementType, [WorkTimeSettlementType::TimeOffCompensation, WorkTimeSettlementType::PayrollPayment], true)) {
                throw new DomainException(sprintf(
                    'Natureza incompatível: a operação "%s" é de crédito, mas a quantidade de minutos é negativa (%d min).',
                    $settlementType->label(),
                    $minutes
                ));
            }

            // 4. Bloqueio em TimeBankAccount para evitar concorrência em saldos do mesmo colaborador
            TimeBankAccount::getOrCreateForEmployee($employee);
            TimeBankAccount::where('employee_id', $employee->id)->lockForUpdate()->first();

            // 5. Resolução autoritativa da origem dos minutos (não confia no chamador)
            $originIdentifier = $data['origin_identifier'] ?? null;
            $settlementService = app(WorkTimeSettlementService::class);
            $availableOrigin = $settlementService->resolveAuthoritativeAvailableMinutes(
                $employee,
                $originType,
                $originIdentifier,
                $referencePeriod
            );

            if ($minutes > 0 && $minutes > $availableOrigin['available_credit']) {
                throw new DomainException(sprintf(
                    'Este saldo já foi destinado ou excede as horas disponíveis (%d min disponíveis, %d min solicitados). Confira o histórico antes de continuar.',
                    $availableOrigin['available_credit'],
                    $minutes
                ));
            }

            if ($minutes < 0 && abs($minutes) > $availableOrigin['available_debit']) {
                throw new DomainException(sprintf(
                    'Este débito já foi regularizado ou excede o débito apurado disponível (%d min disponíveis, %d min solicitados). Confira o histórico antes de continuar.',
                    $availableOrigin['available_debit'],
                    abs($minutes)
                ));
            }

            $operationDate = isset($data['operation_date'])
                ? Carbon::parse($data['operation_date'])
                : Carbon::today();

            $policy = $data['settlement_policy_id'] ?? null
                ? WorkTimeSettlementPolicy::find($data['settlement_policy_id'])
                : $employee->getSettlementPolicyForDate($operationDate);

            $settlement = WorkTimeSettlement::create([
                'employee_id' => $employee->id,
                'closed_period_id' => $data['closed_period_id'] ?? null,
                'settlement_policy_id' => $policy?->id,
                'policy_version' => $data['policy_version'] ?? ($policy ? "v{$policy->id}" : 'default'),
                'reference_period' => $referencePeriod,
                'minutes' => $minutes,
                'settlement_type' => $settlementType,
                'status' => $initialStatus,
                'origin_type' => $originType,
                'origin_identifier' => $originIdentifier,
                'operation_date' => $operationDate->toDateString(),
                'execution_date' => isset($data['execution_date']) ? Carbon::parse($data['execution_date'])->toDateString() : null,
                'document_reference' => $data['document_reference'] ?? null,
                'justification' => $data['justification'] ?? 'Registro de destinação de jornada',
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => $actor->id,
                'approved_by' => $initialStatus === WorkTimeSettlementStatus::Approved ? $actor->id : null,
                'approved_at' => $initialStatus === WorkTimeSettlementStatus::Approved ? now() : null,
                'metadata' => [
                    'source_authoritative' => $availableOrigin['source_description'],
                    'history' => [
                        [
                            'status' => $initialStatus->value,
                            'actor_id' => $actor->id,
                            'actor_name' => $actor->name,
                            'timestamp' => now()->toIso8601String(),
                            'notes' => 'Criação do registro de destinação com validação autoritativa de saldo',
                        ],
                    ],
                ],
            ]);

            Log::info('work_time_settlement.created', [
                'settlement_id' => $settlement->id,
                'employee_id' => $employee->id,
                'minutes' => $minutes,
                'type' => $settlementType->value,
                'status' => $initialStatus->value,
                'actor' => $actor->id,
            ]);

            return $settlement;
        });
    }

    /**
     * Aprova formalmente a destinação de horas.
     * IMPORTANTE: A aprovação autoriza o procedimento, mas NÃO comprova a quitação/execução fática.
     */
    public function approveSettlement(WorkTimeSettlement $settlement, User $actor, ?string $notes = null): WorkTimeSettlement
    {
        return DB::transaction(function () use ($settlement, $actor, $notes) {
            $settlement = WorkTimeSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOperation($actor, $settlement->employee, 'approve', $settlement);

            if (! $settlement->status->canTransitionTo(WorkTimeSettlementStatus::Approved)) {
                throw new DomainException(sprintf(
                    'Não é possível aprovar uma destinação com status "%s". Apenas destinações pendentes podem ser aprovadas.',
                    $settlement->status->label()
                ));
            }

            $history = $settlement->metadata['history'] ?? [];
            $history[] = [
                'status' => WorkTimeSettlementStatus::Approved->value,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'timestamp' => now()->toIso8601String(),
                'notes' => $notes ?? 'Aprovação administrativa da destinação (não constitui comprovante de quitação fática)',
            ];

            $settlement->update([
                'status' => WorkTimeSettlementStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'metadata' => array_merge($settlement->metadata ?? [], ['history' => $history]),
            ]);

            Log::info('work_time_settlement.approved', [
                'settlement_id' => $settlement->id,
                'actor' => $actor->id,
            ]);

            return $settlement;
        });
    }

    /**
     * Marca a destinação como aguardando execução (ex: enviada para o lote da folha ou agendada para folga futura).
     */
    public function markAwaitingExecution(WorkTimeSettlement $settlement, User $actor, ?string $documentRef = null, ?string $notes = null): WorkTimeSettlement
    {
        return DB::transaction(function () use ($settlement, $actor, $documentRef, $notes) {
            $settlement = WorkTimeSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOperation($actor, $settlement->employee, 'mark_awaiting', $settlement);

            if (! $settlement->status->canTransitionTo(WorkTimeSettlementStatus::AwaitingExecution)) {
                throw new DomainException(sprintf(
                    'Apenas destinações previamente aprovadas podem aguardar execução. Status atual: "%s".',
                    $settlement->status->label()
                ));
            }

            $history = $settlement->metadata['history'] ?? [];
            $history[] = [
                'status' => WorkTimeSettlementStatus::AwaitingExecution->value,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'timestamp' => now()->toIso8601String(),
                'notes' => $notes ?? 'Destinação encaminhada para lote / agendamento de execução',
            ];

            $settlement->update([
                'status' => WorkTimeSettlementStatus::AwaitingExecution,
                'document_reference' => $documentRef ?? $settlement->document_reference,
                'metadata' => array_merge($settlement->metadata ?? [], ['history' => $history]),
            ]);

            return $settlement;
        });
    }

    /**
     * Confirma a execução fática da destinação (pagamento confirmado, folga usufruída, compensação realizada).
     * Exige comprovante documental e data de execução. Impede dupla execução e duplicidade de baixa.
     */
    public function confirmExecution(
        WorkTimeSettlement $settlement,
        User $actor,
        CarbonInterface|string $executionDate,
        string $documentReference,
        ?string $notes = null,
        bool $dischargeFromTimeBank = false,
    ): WorkTimeSettlement {
        return DB::transaction(function () use ($settlement, $actor, $executionDate, $documentReference, $notes, $dischargeFromTimeBank) {
            $settlement = WorkTimeSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOperation($actor, $settlement->employee, 'execute', $settlement);

            if ($settlement->status === WorkTimeSettlementStatus::Executed) {
                throw new DomainException('Esta destinação já foi efetivada e possui quitação documentada.');
            }

            if (! $settlement->status->canTransitionTo(WorkTimeSettlementStatus::Executed)) {
                throw new DomainException(sprintf(
                    'Para registrar quitação efetiva, a destinação precisa estar Aprovada ou Aguardando Execução. Status atual: "%s".',
                    $settlement->status->label()
                ));
            }

            $docRefTrim = trim($documentReference);
            if (empty($docRefTrim)) {
                throw new DomainException('A quitação/efetivação exige obrigatoriamente uma referência documental comprobatória (holerite, recibo, portaria ou termo).');
            }

            // Unicidade estrita: impede duas baixas para a mesma execução
            $existingDischarge = WorkTimeSettlementDischarge::where('work_time_settlement_id', $settlement->id)
                ->lockForUpdate()
                ->first();

            if ($existingDischarge) {
                throw new DomainException('Já existe uma quitação documentada vinculada a esta destinação.');
            }

            $execDate = $executionDate instanceof CarbonInterface
                ? $executionDate
                : Carbon::parse($executionDate);

            // Se a destinação der baixa em horas do banco de horas:
            if ($dischargeFromTimeBank) {
                $account = TimeBankAccount::getOrCreateForEmployee($settlement->employee);
                TimeBankAccount::where('id', $account->id)->lockForUpdate()->first();

                $invertMinutes = -$settlement->minutes; // Inverte o sinal para baixar o saldo contábil
                app(RecordTimeBankTransactionAction::class)->execute(
                    employee: $settlement->employee_id,
                    type: $settlement->minutes > 0 ? TimeBankTransactionType::CompensationDebit : TimeBankTransactionType::ManualCredit,
                    minutes: $invertMinutes,
                    referenceDate: $execDate,
                    description: sprintf('Baixa contábil por quitação comprovada (%s - %s)', $settlement->settlement_type->label(), $docRefTrim),
                    reason: $notes ?? sprintf('Efetivação de destinação %s ref %s', $settlement->id, $settlement->reference_period),
                    createdBy: $actor,
                    sourceType: WorkTimeSettlement::class,
                    sourceId: (string) $settlement->id,
                );
            }

            $history = $settlement->metadata['history'] ?? [];
            $history[] = [
                'status' => WorkTimeSettlementStatus::Executed->value,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'timestamp' => now()->toIso8601String(),
                'execution_date' => $execDate->toDateString(),
                'document_reference' => $docRefTrim,
                'notes' => $notes ?? 'Quitação efetivada e comprovada documentalmente',
            ];

            $settlement->update([
                'status' => WorkTimeSettlementStatus::Executed,
                'execution_date' => $execDate->toDateString(),
                'document_reference' => $docRefTrim,
                'executed_by' => $actor->id,
                'executed_at' => now(),
                'metadata' => array_merge($settlement->metadata ?? [], ['history' => $history]),
            ]);

            // 2. Registro no ledger de quitações documentadas com vínculo único
            WorkTimeSettlementDischarge::create([
                'employee_id' => $settlement->employee_id,
                'closed_period_id' => $settlement->closed_period_id,
                'work_time_settlement_id' => $settlement->id,
                'discharge_type' => match ($settlement->settlement_type) {
                    WorkTimeSettlementType::PayrollPayment => SettlementDischargeType::PayrollPaid,
                    WorkTimeSettlementType::PayrollDeduction => SettlementDischargeType::PayrollDeducted,
                    WorkTimeSettlementType::TimeOffCompensation => SettlementDischargeType::TimeOffEnjoyed,
                    WorkTimeSettlementType::CarryOver => SettlementDischargeType::CarriedOver,
                    WorkTimeSettlementType::DeficitCompensation => SettlementDischargeType::CompensatedInPeriod,
                    WorkTimeSettlementType::LegacyMonthlyReset => SettlementDischargeType::MonthlyResetLegacy,
                    default => SettlementDischargeType::TermOfSettlement,
                },
                'minutes' => $settlement->minutes,
                'reference_period' => $settlement->reference_period,
                'execution_date' => $execDate->toDateString(),
                'document_reference' => $docRefTrim,
                'description' => sprintf('Quitação de %s ref %s: %s', $settlement->settlement_type->label(), $settlement->reference_period, $settlement->formattedMinutes()),
                'approved_by' => $settlement->approved_by,
                'created_by' => $actor->id,
                'metadata' => [
                    'work_time_settlement_id' => $settlement->id,
                ],
            ]);

            Log::info('work_time_settlement.executed', [
                'settlement_id' => $settlement->id,
                'execution_date' => $execDate->toDateString(),
                'document_reference' => $docRefTrim,
                'actor' => $actor->id,
            ]);

            return $settlement;
        });
    }

    /**
     * Estorna auditavelmente uma destinação previamente efetivada.
     * Não apaga o registro original; marca como Reversed e cria uma operação compensatória inversa.
     */
    public function reverseSettlement(
        WorkTimeSettlement $settlement,
        User $actor,
        string $justification,
        ?string $documentReference = null,
        bool $reverseTimeBank = false,
    ): WorkTimeSettlement {
        return DB::transaction(function () use ($settlement, $actor, $justification, $documentReference, $reverseTimeBank) {
            $settlement = WorkTimeSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOperation($actor, $settlement->employee, 'reverse', $settlement);

            if ($settlement->status === WorkTimeSettlementStatus::Reversed) {
                throw new DomainException('Esta destinação já foi estornada anteriormente.');
            }

            if (! $settlement->status->canTransitionTo(WorkTimeSettlementStatus::Reversed)) {
                throw new DomainException(sprintf(
                    'Apenas destinações Efetivadas podem ser estornadas. Status atual: "%s". Para outras situações, utilize o cancelamento.',
                    $settlement->status->label()
                ));
            }

            $justificationTrim = trim($justification);
            if (empty($justificationTrim)) {
                throw new DomainException('O estorno exige obrigatoriamente uma justificativa fundamentada.');
            }

            // 1. Marcar registro original como Reversed
            $history = $settlement->metadata['history'] ?? [];
            $history[] = [
                'status' => WorkTimeSettlementStatus::Reversed->value,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'timestamp' => now()->toIso8601String(),
                'notes' => "Operação estornada: {$justificationTrim}",
            ];

            $settlement->update([
                'status' => WorkTimeSettlementStatus::Reversed,
                'metadata' => array_merge($settlement->metadata ?? [], ['history' => $history]),
            ]);

            // 2. Se afetou o TimeBank, estorna a movimentação
            if ($reverseTimeBank) {
                $account = TimeBankAccount::getOrCreateForEmployee($settlement->employee);
                TimeBankAccount::where('id', $account->id)->lockForUpdate()->first();

                app(RecordTimeBankTransactionAction::class)->execute(
                    employee: $settlement->employee_id,
                    type: $settlement->minutes > 0 ? TimeBankTransactionType::ManualCredit : TimeBankTransactionType::CompensationDebit,
                    minutes: $settlement->minutes, // Devolve os minutos originais
                    referenceDate: Carbon::today(),
                    description: sprintf('Estorno contábil de quitação ref %s', $settlement->id),
                    reason: $justificationTrim,
                    createdBy: $actor,
                    sourceType: WorkTimeSettlement::class,
                    sourceId: (string) $settlement->id,
                );
            }

            // 3. Criar a operação compensatória com saldo estritamente invertido
            $invertedMinutes = -$settlement->minutes;
            $reversalSettlement = WorkTimeSettlement::create([
                'employee_id' => $settlement->employee_id,
                'closed_period_id' => $settlement->closed_period_id,
                'settlement_policy_id' => $settlement->settlement_policy_id,
                'policy_version' => $settlement->policy_version,
                'reference_period' => $settlement->reference_period,
                'minutes' => $invertedMinutes,
                'settlement_type' => $settlement->settlement_type,
                'status' => WorkTimeSettlementStatus::Executed,
                'origin_type' => $settlement->origin_type,
                'origin_identifier' => $settlement->origin_identifier,
                'operation_date' => Carbon::today()->toDateString(),
                'execution_date' => Carbon::today()->toDateString(),
                'document_reference' => $documentReference ?? ('ESTORNO-'.$settlement->id),
                'justification' => "Lançamento compensatório de estorno do settlement {$settlement->id}: {$justificationTrim}",
                'reversal_of_id' => $settlement->id,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'executed_by' => $actor->id,
                'executed_at' => now(),
                'metadata' => [
                    'reversal_target_id' => $settlement->id,
                    'history' => [
                        [
                            'status' => WorkTimeSettlementStatus::Executed->value,
                            'actor_id' => $actor->id,
                            'actor_name' => $actor->name,
                            'timestamp' => now()->toIso8601String(),
                            'notes' => 'Lançamento compensatório gerado por estorno auditável',
                        ],
                    ],
                ],
            ]);

            Log::info('work_time_settlement.reversed', [
                'original_id' => $settlement->id,
                'reversal_id' => $reversalSettlement->id,
                'actor' => $actor->id,
                'reason' => $justificationTrim,
            ]);

            return $reversalSettlement;
        });
    }

    /**
     * Cancela uma destinação que ainda não foi executada faticamente.
     */
    public function cancelSettlement(WorkTimeSettlement $settlement, User $actor, string $reason): WorkTimeSettlement
    {
        return DB::transaction(function () use ($settlement, $actor, $reason) {
            $settlement = WorkTimeSettlement::where('id', $settlement->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOperation($actor, $settlement->employee, 'cancel', $settlement);

            if ($settlement->status === WorkTimeSettlementStatus::Cancelled) {
                throw new DomainException('Esta destinação já foi cancelada anteriormente.');
            }

            if (! $settlement->status->canTransitionTo(WorkTimeSettlementStatus::Cancelled)) {
                throw new DomainException(sprintf(
                    'Destinações em estado final (%s) não podem ser canceladas. Se já foram efetivadas, utilize o estorno compensatório.',
                    $settlement->status->label()
                ));
            }

            $reasonTrim = trim($reason);
            if (empty($reasonTrim)) {
                throw new DomainException('O cancelamento exige uma justificativa.');
            }

            $history = $settlement->metadata['history'] ?? [];
            $history[] = [
                'status' => WorkTimeSettlementStatus::Cancelled->value,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'timestamp' => now()->toIso8601String(),
                'notes' => "Destinação cancelada: {$reasonTrim}",
            ];

            $settlement->update([
                'status' => WorkTimeSettlementStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'metadata' => array_merge($settlement->metadata ?? [], ['history' => $history]),
            ]);

            Log::info('work_time_settlement.cancelled', [
                'settlement_id' => $settlement->id,
                'actor' => $actor->id,
                'reason' => $reasonTrim,
            ]);

            return $settlement;
        });
    }

    /**
     * Valida no servidor permissões, vínculos, escopo e segregação de funções.
     * Não confia em permissões de rota ou do lado do cliente.
     */
    public function authorizeOperation(
        User $actor,
        Employee $employee,
        string $action,
        WorkTimeSettlementStatus|WorkTimeSettlement|null $targetOrSettlement = null
    ): void {
        $targetStatus = $targetOrSettlement instanceof WorkTimeSettlementStatus
            ? $targetOrSettlement
            : ($targetOrSettlement instanceof WorkTimeSettlement ? $targetOrSettlement->status : null);
        if (! $actor->exists) {
            throw new DomainException('Acesso negado: usuário não autenticado.');
        }

        if ($actor->isAuditor()) {
            throw new DomainException('Auditores possuem acesso estritamente para consulta e fiscalização de registros.');
        }

        if ($actor->isEmployee()) {
            if (in_array($action, ['approve', 'execute', 'reverse'], true)) {
                throw new DomainException('Colaboradores não possuem permissão para autorizar, aprovar ou efetivar quitações de horas.');
            }

            if ($action === 'create' && in_array($targetStatus, [WorkTimeSettlementStatus::Approved, WorkTimeSettlementStatus::Executed], true)) {
                throw new DomainException('Colaboradores não podem autorizar a própria quitação por manipulação de requisição.');
            }

            if ($employee->user_id !== $actor->id) {
                throw new DomainException('Não é permitido operar registros de outro colaborador.');
            }

            return;
        }

        if ($actor->isManager()) {
            // Segregação de Funções: Gestor não pode aprovar nem efetivar quitação dele próprio
            if (in_array($action, ['approve', 'execute'], true) && $employee->user_id === $actor->id) {
                throw new DomainException('Segregação de funções: um gestor não pode aprovar ou efetivar a própria quitação.');
            }

            if ($action === 'create' && $employee->user_id === $actor->id && in_array($targetStatus, [WorkTimeSettlementStatus::Approved, WorkTimeSettlementStatus::Executed], true)) {
                throw new DomainException('Segregação de funções: um gestor não pode autoaprovar a própria destinação.');
            }

            // Delimitação por Escopo Setorial
            $employeeSectorId = $employee->sector_id;
            $isManaged = $employeeSectorId && Sector::where('manager_id', $actor->id)->where('id', $employeeSectorId)->exists();

            if (! $isManaged) {
                throw new DomainException('O gestor não possui autorização para operar colaboradores fora de seu escopo de gestão.');
            }

            return;
        }

        if ($actor->isAdmin()) {
            return;
        }

        throw new DomainException('Acesso negado: perfil sem autorização para operações de destinação.');
    }

    /**
     * Retorna o saldo total de minutos com destinação pendente para o colaborador na competência.
     */
    public function getPendingMinutes(Employee $employee, string $referencePeriod): int
    {
        return (int) WorkTimeSettlement::where('employee_id', $employee->id)
            ->where('reference_period', $referencePeriod)
            ->whereIn('status', [WorkTimeSettlementStatus::Pending, WorkTimeSettlementStatus::Approved, WorkTimeSettlementStatus::AwaitingExecution])
            ->sum('minutes');
    }

    /**
     * Retorna o saldo total de minutos com quitação efetivada para o colaborador na competência.
     */
    public function getExecutedMinutes(Employee $employee, string $referencePeriod): int
    {
        return (int) WorkTimeSettlement::where('employee_id', $employee->id)
            ->where('reference_period', $referencePeriod)
            ->where('status', WorkTimeSettlementStatus::Executed)
            ->sum('minutes');
    }
}
