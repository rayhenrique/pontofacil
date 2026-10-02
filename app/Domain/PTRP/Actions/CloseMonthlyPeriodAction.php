<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkSchedule;
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

        return DB::transaction(function () use ($year, $month, $closedBy, $notes, $existingPeriod, $periodStart, $periodEnd, $policy) {
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
                    'establishment_id' => $establishment?->id,
                    'establishment_name' => $establishment?->name,
                    'establishment_identifier' => $establishment?->identifier_number,
                ];

                // b) Snapshot de escala
                $schedule = $employee->workSchedule ?? WorkSchedule::first() ?? WorkSchedule::createDefault40h();
                $scheduleSnapshot = [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'type' => $schedule->type,
                    'weekly_hours' => $schedule->weekly_hours,
                    'tolerance_minutes' => $schedule->tolerance_minutes,
                    'daily_tolerance_minutes' => $schedule->daily_tolerance_minutes,
                    'days_config' => $schedule->days_config,
                ];

                // c) Apuração diária e snapshot de jornadas
                $daysInMonth = $periodEnd->day;
                $journeysData = [];
                $empPunchesCount = 0;

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dayDate = Carbon::createFromDate($year, $month, $day);
                    $calculated = $calculateJourneyAction->execute($employee, $dayDate, $schedule);
                    $journeyArray = $calculated->toArray();
                    $journeysData[] = $journeyArray;

                    $empPunchesCount += count($journeyArray['effective_punches'] ?? []);
                }

                $totalPunchesCount += $empPunchesCount;

                // d) Snapshot de tratamentos aprovados na competência
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

                // e) Snapshot e movimentação de Banco de Horas (21.0 - saldo estritamente até o fim da competência)
                $account = TimeBankAccount::getOrCreateForEmployee($employee);
                $balanceBefore = $account->balanceUntil($periodStart->copy()->subSecond());
                $balanceAtClosing = $account->balanceUntil($periodEnd);
                $resetApplied = 0;

                if ($policy && $policy->enabled && $policy->closing_mode === TimeBankClosingMode::MonthlyReset) {
                    if ($balanceAtClosing !== 0) {
                        $resetApplied = -$balanceAtClosing; // Inverte o sinal para zerar o saldo contábil da competência

                        $recordTimeBankTxAction->execute(
                            employee: $employee->id,
                            type: TimeBankTransactionType::MonthlyReset,
                            minutes: $resetApplied,
                            referenceDate: $periodEnd,
                            description: sprintf('Zeramento de fechamento da competência %02d/%04d', $month, $year),
                            reason: 'Aplicação da política MONTHLY_RESET no fechamento formal do período',
                            createdBy: $closedBy,
                            sourceType: ClosedPeriod::class,
                            sourceId: (string) $closedPeriod->id,
                        );

                        Log::info('time_bank.monthly_reset', [
                            'account_id' => $account->id,
                            'employee_id' => $employee->id,
                            'period' => sprintf('%02d/%04d', $month, $year),
                            'previous_balance' => $balanceAtClosing,
                            'reset_minutes' => $resetApplied,
                            'closed_by' => $closedBy->id,
                        ]);
                    }
                }

                $timeBankSnapshot = [
                    'policy_mode' => $policy?->enabled ? $policy->closing_mode->value : 'DISABLED',
                    'balance_before' => $balanceBefore,
                    'balance_at_closing' => $balanceAtClosing,
                    'reset_applied' => $resetApplied,
                    'final_balance' => ($policy?->enabled && $policy->closing_mode === TimeBankClosingMode::MonthlyReset) ? 0 : $balanceAtClosing,
                ];

                // f) Hash individual do snapshot do trabalhador (SHA-256 canônico)
                $canonicalPayload = json_encode([
                    'employee' => $employeeSnapshot,
                    'schedule' => $scheduleSnapshot,
                    'journey' => $journeysData,
                    'treatments' => $treatmentsData,
                    'time_bank' => $timeBankSnapshot,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $empSnapshotHash = hash('sha256', $canonicalPayload);
                $employeeHashes[$employee->id] = $empSnapshotHash;

                // g) Gravar snapshot imutável
                ClosedPeriodEmployeeSnapshot::create([
                    'closed_period_id' => $closedPeriod->id,
                    'employee_id' => $employee->id,
                    'version' => $snapshotVersion,
                    'employee_snapshot' => $employeeSnapshot,
                    'schedule_snapshot' => $scheduleSnapshot,
                    'journey_snapshot' => $journeysData,
                    'treatment_snapshot' => $treatmentsData,
                    'time_bank_snapshot' => $timeBankSnapshot,
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
