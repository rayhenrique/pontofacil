<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\Settlement\Enums\SettlementDischargeType;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\SettlementOriginType;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Enums\WorkTimeSettlementType;
use App\Domain\Settlement\Services\WorkTimeSettlementManager;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Models\CalendarEvent;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\ShiftAssignment;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\TimeBankTransaction;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlement;
use App\Models\WorkTimeSettlementDischarge;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloseMonthlyPeriodAction
{
    /**
     * Fecha formalmente uma competência mensal, realizando o congelamento analítico
     * de todos os empregados em snapshots imutáveis e aplicando as regras de banco de horas.
     */
    public function execute(
        int $year,
        int $month,
        User $closedBy,
        ?string $notes = null,
        bool $allowLegacyUnprovenReset = false,
    ): ClosedPeriod {
        $existingPeriod = ClosedPeriod::findForPeriod($year, $month);

        if ($existingPeriod && $existingPeriod->status === 'closed') {
            throw new \DomainException(sprintf('A competência %02d/%04d já se encontra fechada e congelada.', $month, $year));
        }

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // 21.4 Bloqueio por Tratamentos Pendentes
        $pendingTreatmentsCount = TreatmentEvent::where('status', TreatmentEventStatus::Pending)
            ->whereBetween('effective_at', [$periodStart, $periodEnd])
            ->count();

        if ($pendingTreatmentsCount > 0) {
            throw new \DomainException(sprintf(
                'Não é possível fechar a competência %02d/%04d: existem %d solicitações de tratamento pendentes de aprovação pelo RH. Revise as pendências em /admin/treatment-requests antes de fechar.',
                $month,
                $year,
                $pendingTreatmentsCount
            ));
        }

        // Localizar política de banco de horas vigente na competência
        $policy = TimeBankPolicy::forDate($periodEnd);

        return DB::transaction(function () use ($year, $month, $closedBy, $notes, $existingPeriod, $periodStart, $periodEnd, $policy, $allowLegacyUnprovenReset) {
            $snapshotVersion = $existingPeriod ? ($existingPeriod->snapshot_version + 1) : 1;

            // 1. Criar ou atualizar registro de ClosedPeriod
            if ($existingPeriod) {
                $existingPeriod->update([
                    'status' => 'closed',
                    'snapshot_version' => $snapshotVersion,
                    'policy_id' => $policy?->id,
                    'policy_snapshot' => $policy ? [
                        'id' => $policy->id,
                        'name' => $policy->name,
                        'enabled' => $policy->enabled,
                        'closing_mode' => $policy->closing_mode->value,
                        'valid_from' => $policy->valid_from?->toDateString(),
                    ] : null,
                    'closed_by' => $closedBy->id,
                    'closed_at' => now(),
                    'reopened_by' => null,
                    'reopened_at' => null,
                    'reopen_reason' => null,
                    'notes' => $notes,
                ]);
                $closedPeriod = $existingPeriod;
            } else {
                $closedPeriod = ClosedPeriod::create([
                    'year' => $year,
                    'month' => $month,
                    'policy_id' => $policy?->id,
                    'policy_snapshot' => $policy ? [
                        'id' => $policy->id,
                        'name' => $policy->name,
                        'enabled' => $policy->enabled,
                        'closing_mode' => $policy->closing_mode->value,
                        'valid_from' => $policy->valid_from?->toDateString(),
                    ] : null,
                    'snapshot_version' => $snapshotVersion,
                    'status' => 'closed',
                    'closed_by' => $closedBy->id,
                    'closed_at' => now(),
                    'notes' => $notes,
                ]);
            }

            // 2. Apurar e Congelar os dados de cada trabalhador
            $employees = Employee::with(['user', 'sector.establishment.company', 'workSchedule'])->get();

            $calculateJourneyAction = app(CalculateDailyJourneyAction::class);
            $recordTimeBankTxAction = app(RecordTimeBankTransactionAction::class);

            $totalPunchesCount = 0;
            $totalTreatmentsCount = 0;
            $employeeHashes = [];

            foreach ($employees as $employee) {
                // a) Snapshot cadastral
                $establishment = $employee->sector?->establishment ?? Establishment::first();
                $employeeSnapshot = [
                    'id' => $employee->id,
                    'name' => $employee->user?->name ?? 'Colaborador '.$employee->id,
                    'cpf' => $employee->cpf,
                    'registration_number' => $employee->registration_number,
                    'job_title' => $employee->job_title,
                    'department' => $employee->sector?->name,
                    'legal_regime' => $employee->legal_regime?->value,
                    'labor_rule_profile_id' => $employee->labor_rule_profile_id,
                    'workload_modality' => $employee->workload_modality?->value,
                    'workload_description' => $employee->getWorkloadDescription(),
                    'establishment_id' => $establishment?->id,
                    'establishment_name' => $establishment?->name,
                    'establishment_identifier' => $establishment?->identifier_number,
                ];

                // b) Snapshot de escala e sua vigência
                $schedule = $employee->getWorkScheduleForDate($periodEnd) ?? $employee->workSchedule ?? WorkSchedule::first();
                $activeAssignment = $employee->workScheduleAssignments()->activeAt($periodEnd)->first();
                $scheduleSnapshot = [
                    'id' => $schedule?->id,
                    'name' => $schedule?->name ?? 'Sem escala atribuída',
                    'code' => $schedule?->code,
                    'modality' => $schedule?->modality?->value,
                    'type' => $schedule?->modality?->value ?? 'standard',
                    'weekly_hours' => $schedule?->expected_weekly_minutes ? round($schedule->expected_weekly_minutes / 60, 1) : 40.0,
                    'tolerance_minutes' => $schedule?->tolerance_minutes ?? 5,
                    'daily_tolerance_minutes' => $schedule?->daily_tolerance_minutes ?? 10,
                    'expected_daily_minutes' => $schedule?->expected_daily_minutes,
                    'expected_weekly_minutes' => $schedule?->expected_weekly_minutes,
                    'cycle_days' => $schedule?->cycle_days,
                    'schedule_data' => $schedule?->schedule_data ?? [],
                    'days_config' => $schedule?->days_config ?? null,
                    'cycle_data' => $schedule?->cycle_data ?? [],
                    'assignment' => $activeAssignment ? [
                        'id' => $activeAssignment->id,
                        'effective_from' => $activeAssignment->effective_from->format('Y-m-d'),
                        'effective_until' => $activeAssignment->effective_until?->format('Y-m-d'),
                        'reason' => $activeAssignment->reason,
                    ] : null,
                ];

                // c) Snapshot de plantões e escalas cíclicas previstos/realizados
                $shifts = ShiftAssignment::where('employee_id', $employee->id)
                    ->forPeriod($periodStart, $periodEnd)
                    ->get();
                $shiftsSnapshot = $shifts->map(fn ($s) => [
                    'id' => $s->id,
                    'shift_code' => $s->shift_code,
                    'start_at_local' => $s->start_at_local->toDateTimeString(),
                    'end_at_local' => $s->end_at_local->toDateTimeString(),
                    'timezone' => $s->timezone,
                    'shift_type' => $s->shift_type?->value,
                    'origin' => $s->origin?->value,
                    'status' => $s->status?->value,
                    'is_day_off' => $s->is_day_off,
                    'is_night_shift' => $s->is_night_shift,
                    'crosses_midnight' => $s->crosses_midnight,
                    'break_minutes' => $s->break_minutes,
                    'expected_work_minutes' => $s->expected_work_minutes,
                ])->values()->all();

                // d) Snapshot do perfil de regras jurídicas aplicado
                $ruleProfile = $employee->getLaborRuleProfileForDate($periodEnd);
                $ruleProfileSnapshot = $ruleProfile?->snapshot();

                // e) Apuração diária e snapshot de jornadas
                $daysInMonth = $periodEnd->day;
                $journeysData = [];
                $empPunchesCount = 0;

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dayDate = Carbon::createFromDate($year, $month, $day);
                    $calculated = $calculateJourneyAction->execute($employee, $dayDate);
                    $journeyArray = $calculated->toArray();
                    $journeysData[] = $journeyArray;

                    $empPunchesCount += count($journeyArray['effective_punches'] ?? []);
                }

                $totalPunchesCount += $empPunchesCount;

                // f) Snapshot de tratamentos aprovados na competência
                $treatments = TreatmentEvent::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                        ->orWhere('employment_id', $employee->id);
                })
                    ->where('status', TreatmentEventStatus::Approved)
                    ->whereBetween('effective_at', [$periodStart, $periodEnd])
                    ->get();

                $treatmentsData = $treatments->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type->value,
                    'effective_at' => $t->effective_at->toDateTimeString(),
                    'reason_text' => $t->reason_text,
                    'reference_punch_id' => $t->reference_punch_id,
                ])->values()->all();

                $totalTreatmentsCount += count($treatmentsData);

                // g) Snapshot e movimentação de Banco de Horas (21.0 - saldo estritamente até o fim da competência)
                // g) Snapshot e movimentação de Banco de Horas e Destinação Auditável (WorkTimeSettlement)
                $account = TimeBankAccount::getOrCreateForEmployee($employee);
                $balanceBefore = $account->balanceUntil($periodStart->copy()->subSecond());
                $balanceAtClosing = $account->balanceUntil($periodEnd);
                $resetApplied = 0;

                $settlementService = app(WorkTimeSettlementService::class);
                $settlementManager = app(WorkTimeSettlementManager::class);
                $settlementPolicy = $settlementService->resolvePolicy($employee, $periodEnd);
                $referencePeriod = sprintf('%04d-%02d', $year, $month);

                // 1. Localizar ou associar destinações já existentes da competência
                $existingSettlements = WorkTimeSettlement::where('employee_id', $employee->id)
                    ->where('reference_period', $referencePeriod)
                    ->whereNotIn('status', [
                        WorkTimeSettlementStatus::Cancelled,
                        WorkTimeSettlementStatus::Reversed,
                    ])
                    ->get();

                foreach ($existingSettlements as $s) {
                    if (! $s->closed_period_id) {
                        $s->update(['closed_period_id' => $closedPeriod->id]);
                    }
                }

                $isResetMode = ($policy && $policy->enabled && $policy->closing_mode === TimeBankClosingMode::MonthlyReset) ||
                    ($settlementPolicy->modality === SettlementModality::LegacyMonthlyReset);

                // 2. Tratamento conforme a modalidade da política
                if ($isResetMode) {
                    if ($balanceAtClosing !== 0) {
                        $authorizedSettlement = $existingSettlements->first(fn ($s) => in_array($s->status, [
                            WorkTimeSettlementStatus::Approved,
                            WorkTimeSettlementStatus::Executed,
                        ], true));

                        // Se há política de destinação moderna explicitamente configurada e NÃO há autorização fática
                        $hasCustomSettlementPolicy = WorkTimeSettlementPolicy::activeAt($periodEnd)
                            ->where(function ($q) use ($employee) {
                                $q->where('employee_id', $employee->id)
                                    ->orWhere('establishment_id', $employee->sector?->establishment_id)
                                    ->orWhere('company_id', $employee->sector?->establishment?->company_id);
                            })->exists();

                        if ($hasCustomSettlementPolicy && ! $authorizedSettlement && ! $allowLegacyUnprovenReset) {
                            // ZERAMENTO SEM COMPROVAÇÃO: O fechamento NÃO zera o saldo no banco de forma fictícia.
                            // Registra o saldo como Pendente em WorkTimeSettlement, preservando os minutos do trabalhador.
                            $settlementManager->createSettlement([
                                'employee_id' => $employee->id,
                                'closed_period_id' => $closedPeriod->id,
                                'settlement_policy_id' => $settlementPolicy->id,
                                'reference_period' => $referencePeriod,
                                'minutes' => $balanceAtClosing,
                                'settlement_type' => WorkTimeSettlementType::LegacyMonthlyReset,
                                'status' => WorkTimeSettlementStatus::Pending,
                                'origin_type' => SettlementOriginType::MonthlyClosingBalance,
                                'operation_date' => $periodEnd->toDateString(),
                                'justification' => sprintf('Fechamento de competência %02d/%04d sem destinação comprovada. Saldo de %s permanece registrado como pendente de quitação.', $month, $year, TimeBankAccount::formatMinutes($balanceAtClosing)),
                                'idempotency_key' => sprintf('CLOSING-PENDING-%d-%04d-%02d', $employee->id, $year, $month),
                            ], $closedBy);

                            $resetApplied = 0; // Saldo do banco NÃO é apagado
                        } else {
                            // Modo de compatibilidade legada com comprovação/autorização ou legado direto
                            $resetApplied = -$balanceAtClosing; // Inverte o sinal para o ajuste compensatório

                            $recordTimeBankTxAction->execute(
                                employee: $employee->id,
                                type: TimeBankTransactionType::MonthlyReset,
                                minutes: $resetApplied,
                                referenceDate: $periodEnd,
                                description: sprintf('Zeramento de fechamento da competência %02d/%04d', $month, $year),
                                reason: 'Aplicação de política MONTHLY_RESET no fechamento formal do período',
                                createdBy: $closedBy,
                                sourceType: ClosedPeriod::class,
                                sourceId: (string) $closedPeriod->id,
                            );

                            $settlementManager->createSettlement([
                                'employee_id' => $employee->id,
                                'closed_period_id' => $closedPeriod->id,
                                'settlement_policy_id' => $settlementPolicy->id,
                                'reference_period' => $referencePeriod,
                                'minutes' => $balanceAtClosing,
                                'settlement_type' => WorkTimeSettlementType::LegacyMonthlyReset,
                                'status' => WorkTimeSettlementStatus::Executed,
                                'origin_type' => SettlementOriginType::MonthlyClosingBalance,
                                'operation_date' => $periodEnd->toDateString(),
                                'execution_date' => $periodEnd->toDateString(),
                                'document_reference' => sprintf('ZERAMENTO-%04d-%02d', $year, $month),
                                'justification' => sprintf('Zeramento contábil no fechamento formal da competência %02d/%04d', $month, $year),
                                'idempotency_key' => sprintf('CLOSING-RESET-%d-%04d-%02d', $employee->id, $year, $month),
                            ], $closedBy);

                            WorkTimeSettlementDischarge::create([
                                'employee_id' => $employee->id,
                                'closed_period_id' => $closedPeriod->id,
                                'discharge_type' => SettlementDischargeType::MonthlyResetLegacy,
                                'minutes' => $resetApplied,
                                'reference_period' => sprintf('%04d-%02d', $year, $month),
                                'execution_date' => $periodEnd->toDateString(),
                                'document_reference' => sprintf('ZERAMENTO-%04d-%02d', $year, $month),
                                'description' => sprintf('Zeramento contábil no fechamento formal da competência %02d/%04d', $month, $year),
                                'approved_by' => $closedBy->id,
                                'created_by' => $closedBy->id,
                                'metadata' => [
                                    'previous_balance' => $balanceAtClosing,
                                    'reset_minutes' => $resetApplied,
                                ],
                            ]);
                        }
                    }
                } elseif ($settlementPolicy->modality === SettlementModality::CumulativeBank) {
                    if ($balanceAtClosing !== 0) {
                        $settlementManager->createSettlement([
                            'employee_id' => $employee->id,
                            'closed_period_id' => $closedPeriod->id,
                            'settlement_policy_id' => $settlementPolicy->id,
                            'reference_period' => $referencePeriod,
                            'minutes' => $balanceAtClosing,
                            'settlement_type' => WorkTimeSettlementType::CarryOver,
                            'status' => WorkTimeSettlementStatus::Executed,
                            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
                            'operation_date' => $periodEnd->toDateString(),
                            'execution_date' => $periodEnd->toDateString(),
                            'document_reference' => sprintf('TRANSPORTE-%04d-%02d', $year, $month),
                            'justification' => sprintf('Saldo transportado no banco de horas para a competência seguinte (%s)', TimeBankAccount::formatMinutes($balanceAtClosing)),
                            'idempotency_key' => sprintf('CARRYOVER-%d-%04d-%02d', $employee->id, $year, $month),
                        ], $closedBy);

                        WorkTimeSettlementDischarge::create([
                            'employee_id' => $employee->id,
                            'closed_period_id' => $closedPeriod->id,
                            'discharge_type' => SettlementDischargeType::CarriedOver,
                            'minutes' => $balanceAtClosing,
                            'reference_period' => sprintf('%04d-%02d', $year, $month),
                            'execution_date' => $periodEnd->toDateString(),
                            'document_reference' => sprintf('TRANSPORTE-%04d-%02d', $year, $month),
                            'description' => sprintf('Saldo transportado no banco de horas para a competência seguinte (%s)', TimeBankAccount::formatMinutes($balanceAtClosing)),
                            'approved_by' => $closedBy->id,
                            'created_by' => $closedBy->id,
                        ]);
                    }
                } elseif ($settlementPolicy->modality === SettlementModality::MonthlyCompensation) {
                    if ($balanceAtClosing !== 0) {
                        // Compensação restrita à competência: saldo residual fica registrado como Pendente de quitação/folha
                        $settlementManager->createSettlement([
                            'employee_id' => $employee->id,
                            'closed_period_id' => $closedPeriod->id,
                            'settlement_policy_id' => $settlementPolicy->id,
                            'reference_period' => $referencePeriod,
                            'minutes' => $balanceAtClosing,
                            'settlement_type' => $balanceAtClosing > 0 ? WorkTimeSettlementType::PayrollPayment : WorkTimeSettlementType::DeficitCompensation,
                            'status' => WorkTimeSettlementStatus::Pending,
                            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
                            'operation_date' => $periodEnd->toDateString(),
                            'justification' => sprintf('Saldo residual da compensação mensal na competência %02d/%04d pendente de destinação.', $month, $year),
                            'idempotency_key' => sprintf('MONTHLY-COMP-PENDING-%d-%04d-%02d', $employee->id, $year, $month),
                        ], $closedBy);
                    }
                } elseif ($settlementPolicy->modality === SettlementModality::DirectPayroll || $settlementPolicy->modality === SettlementModality::NoBank) {
                    $totalPayrollOvertimeMinutes = (int) collect($journeysData)->sum('destined_to_payroll_minutes');
                    if ($totalPayrollOvertimeMinutes > 0) {
                        // Horas extras destinadas à folha registradas como Aprovadas / Aguardando Execução (não quitadas até confirmação)
                        $settlementManager->createSettlement([
                            'employee_id' => $employee->id,
                            'closed_period_id' => $closedPeriod->id,
                            'settlement_policy_id' => $settlementPolicy->id,
                            'reference_period' => $referencePeriod,
                            'minutes' => $totalPayrollOvertimeMinutes,
                            'settlement_type' => WorkTimeSettlementType::PayrollPayment,
                            'status' => WorkTimeSettlementStatus::AwaitingExecution,
                            'origin_type' => SettlementOriginType::MonthlyClosingBalance,
                            'operation_date' => $periodEnd->toDateString(),
                            'justification' => sprintf('Horas extras apuradas destinadas à folha de pagamento na competência %02d/%04d', $month, $year),
                            'idempotency_key' => sprintf('PAYROLL-DESTINED-%d-%04d-%02d', $employee->id, $year, $month),
                        ], $closedBy);
                    }
                }

                $periodTransactions = TimeBankTransaction::where('time_bank_account_id', $account->id)
                    ->whereBetween('reference_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orderBy('reference_date', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get()
                    ->map(fn ($tx) => [
                        'id' => $tx->id,
                        'reference_date' => $tx->reference_date->toDateString(),
                        'minutes' => (int) $tx->minutes,
                        'type' => $tx->type->value,
                        'tipo_mov_bh' => ($tx->minutes > 0) ? '1' : '2',
                    ])
                    ->values()
                    ->all();

                $timeBankSnapshot = [
                    'policy_mode' => $policy?->enabled ? $policy->closing_mode->value : ($settlementPolicy->operatesTimeBank() ? 'CARRY_OVER' : 'DISABLED'),
                    'balance_before' => $balanceBefore,
                    'balance_at_closing' => $balanceAtClosing,
                    'reset_applied' => $resetApplied,
                    'final_balance' => ($resetApplied !== 0) ? 0 : $balanceAtClosing,
                    'transactions' => $periodTransactions,
                    'settlement_policy' => $settlementPolicy->snapshot(),
                    'pending_settlements_minutes' => $settlementManager->getPendingMinutes($employee, $referencePeriod),
                    'executed_settlements_minutes' => $settlementManager->getExecutedMinutes($employee, $referencePeriod),
                ];

                // h) Snapshot do calendário laboral utilizado na competência (20.18.18)
                $calendarEvents = CalendarEvent::forMonth($year, $month, $establishment)
                    ->get();
                $calendarSnapshotData = $calendarEvents->map(fn (CalendarEvent $e) => $e->toSnapshotArray())->values()->all();

                // i) Hash individual do snapshot do trabalhador (SHA-256 canônico)
                $canonicalPayload = json_encode([
                    'employee' => $employeeSnapshot,
                    'schedule' => $scheduleSnapshot,
                    'shifts' => $shiftsSnapshot,
                    'rule_profile' => $ruleProfileSnapshot,
                    'journey' => $journeysData,
                    'treatments' => $treatmentsData,
                    'time_bank' => $timeBankSnapshot,
                    'calendar' => $calendarSnapshotData,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $empSnapshotHash = hash('sha256', $canonicalPayload);
                $employeeHashes[$employee->id] = $empSnapshotHash;

                // j) Gravar snapshot imutável
                ClosedPeriodEmployeeSnapshot::create([
                    'closed_period_id' => $closedPeriod->id,
                    'employee_id' => $employee->id,
                    'version' => $snapshotVersion,
                    'employee_snapshot' => $employeeSnapshot,
                    'schedule_snapshot' => $scheduleSnapshot,
                    'shifts_snapshot' => $shiftsSnapshot,
                    'rule_profile_snapshot' => $ruleProfileSnapshot,
                    'journey_snapshot' => $journeysData,
                    'treatment_snapshot' => $treatmentsData,
                    'time_bank_snapshot' => $timeBankSnapshot,
                    'calendar_snapshot' => $calendarSnapshotData,
                    'snapshot_hash' => $empSnapshotHash,
                ]);
            }

            // 3. Hash agregado determinístico da competência
            ksort($employeeHashes);
            $aggregatedPayload = sprintf(
                'PERIOD:%04d-%02d|V:%d|EMPLOYEES:%d|HASHES:%s',
                $year,
                $month,
                $snapshotVersion,
                count($employees),
                implode(',', $employeeHashes)
            );
            $periodSnapshotHash = hash('sha256', $aggregatedPayload);

            $closedPeriod->update([
                'snapshot_hash' => $periodSnapshotHash,
                'employees_count' => count($employees),
                'punches_count' => $totalPunchesCount,
                'treatments_count' => $totalTreatmentsCount,
                'generated_at' => now(),
            ]);

            Log::info('period.closed', [
                'year' => $year,
                'month' => $month,
                'version' => $snapshotVersion,
                'closed_by' => $closedBy->id,
                'policy_mode' => $policy?->closing_mode?->value ?? 'DISABLED',
                'employees_count' => count($employees),
                'punches_count' => $totalPunchesCount,
            ]);

            Log::info('period.snapshot_generated', [
                'period_id' => $closedPeriod->id,
                'year' => $year,
                'month' => $month,
                'version' => $snapshotVersion,
                'hash' => $periodSnapshotHash,
            ]);

            return $closedPeriod;
        });
    }
}
