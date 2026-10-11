<?php

namespace App\Domain\PTRP\Services;

use App\Domain\PTRP\Actions\CalculateDailyJourneyAction;
use App\Domain\PTRP\DTOs\CalculatedJourney;
use App\Domain\PTRP\DTOs\MonthlyDifferencesSummary;
use App\Domain\PTRP\DTOs\TimeBankMonthlySummary;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\Settlement\Enums\SettlementModality;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\PunchEvent;
use App\Models\TimeBankPolicy;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use App\Models\User;
use App\Models\WorkTimeSettlement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TimesheetJourneyService
{
    public function __construct(
        protected CalculateDailyJourneyAction $calculateDailyJourneyAction,
        protected TimeBankStatementService $timeBankStatementService,
        protected ?TimeBankReconciliationService $reconciliationService = null,
    ) {
        $this->reconciliationService ??= app(TimeBankReconciliationService::class);
    }

    /**
     * Resolve de forma soberana e integrada ao PTRP todas as informações do espelho de ponto:
     * - Utiliza snapshots congelados quando a competência estiver fechada;
     * - Executa o motor analítico CalculateDailyJourneyAction quando estiver aberta;
     * - Classifica estados de jornada de forma consistente (concluída, em andamento, incompleta, etc.);
     * - Apura indicadores com separação rigorosa de critérios e identificação de dados provisórios/parciais.
     *
     * @return array<string, mixed>
     */
    public function resolveMonthData(User $targetUser, int $year, int $month): array
    {
        $employee = Employee::with(['sector.establishment', 'workSchedule'])
            ->where('user_id', $targetUser->id)
            ->first();

        // Se o usuário não possuir registro de Employee, cria ou vincula perfil mínimo
        if (! $employee) {
            $employee = Employee::firstOrCreate(
                ['user_id' => $targetUser->id],
                [
                    'cpf' => '00000000000',
                    'registration_number' => str_pad((string) $targetUser->id, 6, '0', STR_PAD_LEFT),
                    'job_title' => $targetUser->role ? $targetUser->role->label() : 'Colaborador',
                ]
            );
        }

        $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $closedPeriod = ClosedPeriod::findForPeriod($year, $month);
        $isClosed = $closedPeriod && $closedPeriod->status === 'closed';

        $policy = TimeBankPolicy::forDate($periodEnd);

        // Cenário 1: Competência Fechada — Carregar do Snapshot Imutável
        if ($isClosed) {
            $snapshot = $closedPeriod->currentSnapshots()
                ->where('employee_id', $employee->id)
                ->first();

            if ($snapshot && ! empty($snapshot->journey_snapshot)) {
                return $this->resolveFromClosedSnapshot(
                    targetUser: $targetUser,
                    employee: $employee,
                    closedPeriod: $closedPeriod,
                    snapshot: $snapshot,
                    policy: $policy,
                    year: $year,
                    month: $month
                );
            }
        }

        // Cenário 2: Competência Aberta — Apuração Analítica em Tempo Real via PTRP
        return $this->resolveFromLiveCalculation(
            targetUser: $targetUser,
            employee: $employee,
            policy: $policy,
            year: $year,
            month: $month,
            periodStart: $periodStart,
            periodEnd: $periodEnd
        );
    }

    /**
     * Monta dados a partir de snapshot congelado de competência fechada.
     */
    protected function resolveFromClosedSnapshot(
        User $targetUser,
        Employee $employee,
        ClosedPeriod $closedPeriod,
        $snapshot,
        ?TimeBankPolicy $policy,
        int $year,
        int $month
    ): array {
        $journeysData = $snapshot->journey_snapshot;
        $timeBankData = $snapshot->time_bank_snapshot;

        $groupedEntries = [];
        $daysCalculated = [];
        $totalWorkedMinutes = 0;
        $completedDaysCount = 0;
        $totalPunchesCount = 0;
        $incompleteDaysCount = 0;

        $positiveMinutes = 0;
        $negativeMinutes = 0;
        $provisionalPositiveMinutes = 0;
        $provisionalNegativeMinutes = 0;
        $concludedDaysCount = 0;
        $pendingDaysCount = 0;
        $uncalculableDaysCount = 0;
        $hasHistoricalUnavailable = false;

        // Recupera batidas brutas para compor a exibição de evidências e coordenadas
        $rawPunchesByDate = $this->loadRawEntriesGroupedByDate($targetUser, $employee, $year, $month);

        foreach ($journeysData as $j) {
            $date = $j['date'];
            $effectivePunches = $j['effective_punches'] ?? [];
            $workedMinutes = (int) ($j['worked_minutes'] ?? 0);
            $isIncomplete = (bool) ($j['is_incomplete'] ?? false);
            $hasSchedule = (bool) ($j['has_schedule'] ?? true);
            $isPendingConfig = (bool) ($j['is_pending_configuration'] ?? false);
            $hasScheduledKey = array_key_exists('scheduled_minutes', $j);
            $scheduledMinutes = $hasScheduledKey ? (int) $j['scheduled_minutes'] : null;

            if ($workedMinutes > 0 || ! empty($effectivePunches) || isset($rawPunchesByDate[$date])) {
                $totalWorkedMinutes += $workedMinutes;
                $punchesCount = count($effectivePunches);
                $totalPunchesCount += $punchesCount;

                if (! $isIncomplete && $workedMinutes > 0) {
                    $completedDaysCount++;
                } elseif ($isIncomplete) {
                    $incompleteDaysCount++;
                }

                $hours = intdiv($workedMinutes, 60);
                $remMinutes = $workedMinutes % 60;

                // Apuração da Diferença a partir do Snapshot Imutável
                if ($scheduledMinutes === null) {
                    $diffState = 'historical_unavailable';
                    $diffMinutes = null;
                    $formattedDiff = 'Histórico indisponível';
                    $diffBadgeClass = 'bg-gray-100 text-gray-600 border-gray-200';
                    $hasHistoricalUnavailable = true;
                    $uncalculableDaysCount++;
                } elseif (! $hasSchedule || $isPendingConfig) {
                    $diffState = 'uncalculable';
                    $diffMinutes = null;
                    $formattedDiff = 'Saldo indisponível — escala não configurada';
                    $diffBadgeClass = 'bg-gray-100 text-gray-700 border-gray-300';
                    $uncalculableDaysCount++;
                } elseif ($isIncomplete) {
                    $diffState = 'pending';
                    $diffMinutes = null;
                    $formattedDiff = 'Apuração pendente';
                    $diffBadgeClass = 'bg-amber-50 text-amber-800 border-amber-300';
                    $pendingDaysCount++;

                    $prelimDiff = $workedMinutes - $scheduledMinutes;
                    if ($prelimDiff > 0) {
                        $provisionalPositiveMinutes += $prelimDiff;
                    } elseif ($prelimDiff < 0) {
                        $provisionalNegativeMinutes += $prelimDiff;
                    }
                } else {
                    $diffState = 'calculated';
                    if (array_key_exists('daily_difference_minutes', $j) && $j['daily_difference_minutes'] !== null) {
                        $diffMinutes = (int) $j['daily_difference_minutes'];
                    } elseif ($workedMinutes === 0 && $scheduledMinutes > 0 && ((int) ($j['absence_minutes'] ?? 0) === 0)) {
                        // Ausência abonada/justificada registrada no snapshot
                        $diffMinutes = 0;
                    } else {
                        $diffMinutes = $workedMinutes - $scheduledMinutes;
                    }

                    $formattedDiff = CalculatedJourney::formatDifferenceMinutes($diffMinutes);
                    $diffBadgeClass = match (true) {
                        $diffMinutes > 0 => 'bg-emerald-50 text-emerald-800 border-emerald-300',
                        $diffMinutes < 0 => 'bg-rose-50 text-rose-800 border-rose-300',
                        default => 'bg-slate-100 text-slate-700 border-slate-300',
                    };

                    $concludedDaysCount++;
                    if ($diffMinutes > 0) {
                        $positiveMinutes += $diffMinutes;
                    } elseif ($diffMinutes < 0) {
                        $negativeMinutes += $diffMinutes;
                    }
                }

                $daysCalculated[$date] = [
                    'minutes' => $workedMinutes,
                    'hours' => $hours,
                    'remMinutes' => $remMinutes,
                    'formatted' => sprintf('%02dh %02dm', $hours, $remMinutes),
                    'status' => 'closed_period',
                    'status_label' => 'Período Fechado',
                    'status_badge_class' => 'bg-slate-100 text-slate-800 border-slate-300',
                    'is_incomplete' => $isIncomplete,
                    'is_open' => false,
                    'notes' => $j['treatment_notes'] ?? [],
                    'scheduled_minutes' => $scheduledMinutes ?? 0,
                    'scheduled_formatted' => $scheduledMinutes !== null ? CalculatedJourney::formatMinutes($scheduledMinutes) : 'N/D',
                    'worked_formatted' => CalculatedJourney::formatMinutes($workedMinutes),
                    'night_minutes' => (int) ($j['physical_night_minutes'] ?? ($j['night_work']['physical_night_minutes'] ?? 0)),
                    'night_equivalent_minutes' => (int) ($j['legal_night_equivalent_minutes'] ?? ($j['night_work']['legal_night_equivalent_minutes'] ?? 0)),
                    'overtime_minutes' => (int) ($j['overtime_minutes'] ?? 0),
                    'has_schedule' => $hasSchedule,
                    'is_pending_configuration' => $isPendingConfig,
                    'is_day_off' => ((int) ($scheduledMinutes ?? 0)) === 0 && $hasSchedule,
                    'shift_code' => $j['shift_code'] ?? null,
                    'treatments' => collect([]),
                    'difference_minutes' => $diffMinutes,
                    'difference_state' => $diffState,
                    'formatted_difference' => $formattedDiff,
                    'difference_badge_class' => $diffBadgeClass,
                    'tolerated_minutes' => (int) ($j['tolerated_minutes'] ?? 0),
                    'justified_minutes' => (int) ($j['justified_minutes'] ?? 0),
                    'settlement_modality' => $j['settlement_modality'] ?? null,
                    'destined_to_payroll_minutes' => (int) ($j['destined_to_payroll_minutes'] ?? 0),
                    'destined_to_compensation_minutes' => (int) ($j['destined_to_compensation_minutes'] ?? 0),
                ];

                $groupedEntries[$date] = $rawPunchesByDate[$date] ?? $this->formatEffectivePunchesAsEntries($effectivePunches, $date);
            }
        }

        krsort($groupedEntries);

        $monthHours = intdiv($totalWorkedMinutes, 60);
        $monthRemMinutes = $totalWorkedMinutes % 60;
        $monthFormatted = sprintf('%dh %02dm', $monthHours, $monthRemMinutes);

        $workedDaysCount = $completedDaysCount;
        $avgMinutes = $workedDaysCount > 0 ? (int) round($totalWorkedMinutes / $workedDaysCount) : 0;
        $avgFormatted = $workedDaysCount > 0 ? sprintf('%02dh %02dm', intdiv($avgMinutes, 60), $avgMinutes % 60) : 'N/D';

        $netMinutes = $positiveMinutes + $negativeMinutes;
        $notes = [
            'Dados congelados em snapshot auditado. Competência fiscalmente fechada.',
            'A diferença líquida é uma apuração puramente matemática entre previstas e trabalhadas e não representa compensação jurídica automática de créditos e débitos.',
        ];

        if ($hasHistoricalUnavailable) {
            $notes[] = 'Campos de previsão ou escala não estavam disponíveis no snapshot legado.';
        }

        $summaryStatus = $hasHistoricalUnavailable && $concludedDaysCount === 0 ? 'historical' : 'definitive';
        $summaryStatusLabel = $summaryStatus === 'historical'
            ? 'Histórico Legado'
            : sprintf('Definitiva (Snapshot imutável v%s)', $closedPeriod->snapshot_version);

        $timeBankSummary = TimeBankMonthlySummary::fromSnapshot($timeBankData, $year, $month, $employee, $closedPeriod);

        $operatesTimeBank = ($timeBankSummary && $timeBankSummary->operatesTimeBank);

        $reconciliationResult = $timeBankSummary
            ? $this->reconciliationService->reconcile($employee, $year, $month, $timeBankSummary, $daysCalculated, $closedPeriod)
            : null;

        $differencesSummary = new MonthlyDifferencesSummary(
            positiveMinutes: $positiveMinutes,
            negativeMinutes: $negativeMinutes,
            netMinutes: $netMinutes,
            status: $summaryStatus,
            statusLabel: $summaryStatusLabel,
            concludedDaysCount: $concludedDaysCount,
            pendingDaysCount: $pendingDaysCount,
            uncalculableDaysCount: $uncalculableDaysCount,
            settlementModality: null,
            operatesTimeBank: $operatesTimeBank,
            provisionalPositiveMinutes: $provisionalPositiveMinutes,
            provisionalNegativeMinutes: $provisionalNegativeMinutes,
            notes: $notes,
            destinationDescription: $operatesTimeBank
                ? 'Competência fechada com registro em banco de horas.'
                : 'Competência fechada sem movimentação de banco de horas.',
        );

        return [
            'isClosedPeriod' => true,
            'closedPeriod' => $closedPeriod,
            'snapshotVersion' => $closedPeriod->snapshot_version,
            'groupedEntries' => $groupedEntries,
            'daysCalculated' => $daysCalculated,
            'totalMonthFormatted' => $monthFormatted,
            'totalWorkedMinutes' => $totalWorkedMinutes,
            'workedDaysCount' => $workedDaysCount,
            'completedDaysCount' => $completedDaysCount,
            'incompleteDaysCount' => $incompleteDaysCount,
            'totalPunches' => $totalPunchesCount,
            'avgFormatted' => $avgFormatted,
            'avgCriteria' => sprintf('Média apurada sobre os %d dias com jornada concluída (Snapshot imutável)', $workedDaysCount),
            'isPartial' => false,
            'timeBankSummary' => $timeBankSummary,
            'reconciliationResult' => $reconciliationResult,
            'policy' => $policy,
            'employee' => $employee,
            'differencesSummary' => $differencesSummary,
            'operatesTimeBank' => $operatesTimeBank,
        ];
    }

    /**
     * Apura dados dinâmicos em tempo real via PTRP quando a competência estiver aberta.
     */
    protected function resolveFromLiveCalculation(
        User $targetUser,
        Employee $employee,
        ?TimeBankPolicy $policy,
        int $year,
        int $month,
        Carbon $periodStart,
        Carbon $periodEnd
    ): array {
        $allMonthTreatments = TreatmentEvent::with(['requester', 'approver', 'rejecter'])
            ->where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                    ->orWhere('employment_id', $employee->id);
            })
            ->whereBetween('effective_at', [$periodStart, $periodEnd])
            ->orderBy('created_at', 'desc')
            ->get();

        $treatmentsByDate = $allMonthTreatments->groupBy(fn ($t) => $t->effective_at->format('Y-m-d'));
        $treatmentsByPunchId = $allMonthTreatments->whereNotNull('reference_punch_id')->groupBy('reference_punch_id');
        $pendingTreatments = $allMonthTreatments->where('status', TreatmentEventStatus::Pending)->groupBy(fn ($t) => $t->effective_at->format('Y-m-d'));

        $rawPunchesByDate = $this->loadRawEntriesGroupedByDate($targetUser, $employee, $year, $month);

        // Identifica todas as datas no mês que possuem marcações ou solicitações de tratamento
        $activeDates = collect(array_keys($rawPunchesByDate))
            ->merge($treatmentsByDate->keys())
            ->unique()
            ->sortDesc()
            ->values();

        $groupedEntries = [];
        $daysCalculated = [];
        $totalWorkedMinutes = 0;
        $completedDaysCount = 0;
        $incompleteDaysCount = 0;
        $inProgressDaysCount = 0;
        $pendingTreatmentDaysCount = 0;
        $totalPunchesCount = 0;

        $positiveMinutes = 0;
        $negativeMinutes = 0;
        $provisionalPositiveMinutes = 0;
        $provisionalNegativeMinutes = 0;
        $concludedDaysCount = 0;
        $pendingDaysCount = 0;
        $uncalculableDaysCount = 0;
        $destinedToPayrollTotal = 0;
        $destinedToCompTotal = 0;

        foreach ($activeDates as $dateStr) {
            $date = Carbon::parse($dateStr);
            $calculated = $this->calculateDailyJourneyAction->execute($employee, $date);

            $workedMinutes = $calculated->workedMinutes;
            $isIncomplete = $calculated->isIncomplete;
            $effectivePunches = $calculated->effectivePunches;
            $hasPendingTreatment = isset($pendingTreatments[$dateStr]);

            $totalWorkedMinutes += $workedMinutes;
            $totalPunchesCount += count($effectivePunches);

            // Determinação de status contextual rigorosa
            $statusInfo = $this->determineDayStatus($date, $calculated, $hasPendingTreatment);

            if ($statusInfo['key'] === 'concluded') {
                $completedDaysCount++;
            } elseif ($statusInfo['key'] === 'in_progress') {
                $inProgressDaysCount++;
            } elseif ($statusInfo['key'] === 'incomplete') {
                $incompleteDaysCount++;
            }

            if ($hasPendingTreatment) {
                $pendingTreatmentDaysCount++;
            }

            $hours = intdiv($workedMinutes, 60);
            $remMinutes = $workedMinutes % 60;

            $dayTreatments = $treatmentsByDate->get($dateStr, collect([]));

            $diffMinutes = $calculated->dailyDifferenceMinutes();
            $diffState = $calculated->differenceState();
            $formattedDiff = $calculated->formattedDifference();

            if ($hasPendingTreatment && $diffState === 'calculated') {
                $diffState = 'partial';
            }

            $diffBadgeClass = match ($diffState) {
                'uncalculable' => 'bg-gray-100 text-gray-700 border-gray-300',
                'pending' => 'bg-amber-50 text-amber-800 border-amber-300',
                'partial' => 'bg-amber-50 text-amber-800 border-amber-300',
                'calculated' => match (true) {
                    $diffMinutes > 0 => 'bg-emerald-50 text-emerald-800 border-emerald-300',
                    $diffMinutes < 0 => 'bg-rose-50 text-rose-800 border-rose-300',
                    default => 'bg-slate-100 text-slate-700 border-slate-300',
                },
                default => 'bg-gray-100 text-gray-700 border-gray-300',
            };

            if ($diffState === 'calculated') {
                $concludedDaysCount++;
                if ($diffMinutes > 0) {
                    $positiveMinutes += $diffMinutes;
                } elseif ($diffMinutes < 0) {
                    $negativeMinutes += $diffMinutes;
                }
                $destinedToPayrollTotal += $calculated->destinedToPayrollMinutes;
                $destinedToCompTotal += $calculated->destinedToCompensationMinutes;
            } elseif ($diffState === 'pending' || $diffState === 'partial') {
                $pendingDaysCount++;
                $pDiff = $workedMinutes - $calculated->scheduledMinutes;
                if ($pDiff > 0) {
                    $provisionalPositiveMinutes += $pDiff;
                } elseif ($pDiff < 0) {
                    $provisionalNegativeMinutes += $pDiff;
                }
            } elseif ($diffState === 'uncalculable') {
                $uncalculableDaysCount++;
            }

            $daysCalculated[$dateStr] = [
                'minutes' => $workedMinutes,
                'hours' => $hours,
                'remMinutes' => $remMinutes,
                'formatted' => sprintf('%02dh %02dm', $hours, $remMinutes),
                'status' => $statusInfo['key'],
                'status_label' => $statusInfo['label'],
                'status_badge_class' => $statusInfo['badge_class'],
                'is_incomplete' => $isIncomplete,
                'is_open' => ($statusInfo['key'] === 'in_progress'),
                'notes' => $calculated->treatmentNotes,
                'scheduled_minutes' => $calculated->scheduledMinutes,
                'scheduled_formatted' => $calculated->formattedScheduled(),
                'worked_formatted' => $calculated->formattedWorked(),
                'night_minutes' => $calculated->nightWorkedMinutes(),
                'night_equivalent_minutes' => $calculated->legalNightEquivalentMinutes(),
                'overtime_minutes' => $calculated->overtimeMinutes,
                'has_schedule' => $calculated->hasSchedule,
                'is_pending_configuration' => $calculated->isPendingConfiguration,
                'is_day_off' => $calculated->scheduledMinutes === 0 && $calculated->hasSchedule,
                'shift_code' => $calculated->shiftCode,
                'has_pending_treatment' => $hasPendingTreatment,
                'treatments' => $dayTreatments,
                'difference_minutes' => $diffMinutes,
                'difference_state' => $diffState,
                'formatted_difference' => $formattedDiff,
                'difference_badge_class' => $diffBadgeClass,
                'tolerated_minutes' => $calculated->toleratedMinutes,
                'justified_minutes' => $calculated->justifiedMinutes,
                'settlement_modality' => $calculated->settlementModality?->value,
                'destined_to_payroll_minutes' => $calculated->destinedToPayrollMinutes,
                'destined_to_compensation_minutes' => $calculated->destinedToCompensationMinutes,
            ];

            $entries = $rawPunchesByDate[$dateStr] ?? collect([]);
            foreach ($entries as $entry) {
                $punchTreatment = $treatmentsByPunchId->get($entry->id)?->first();
                $entry->treatment = $punchTreatment;
                $entry->is_disregarded = ($punchTreatment && $punchTreatment->type === TreatmentEventType::PunchDisregarded && $punchTreatment->status === TreatmentEventStatus::Approved);
                $entry->has_pending_disregard = ($punchTreatment && $punchTreatment->type === TreatmentEventType::PunchDisregarded && $punchTreatment->status === TreatmentEventStatus::Pending);
                $entry->has_rejected_disregard = ($punchTreatment && $punchTreatment->type === TreatmentEventType::PunchDisregarded && $punchTreatment->status === TreatmentEventStatus::Rejected);
            }
            $groupedEntries[$dateStr] = $entries;
        }

        $monthHours = intdiv($totalWorkedMinutes, 60);
        $monthRemMinutes = $totalWorkedMinutes % 60;
        $monthFormatted = sprintf('%dh %02dm', $monthHours, $monthRemMinutes);

        $workedDaysCount = $completedDaysCount;
        $hasIncompleteData = ($incompleteDaysCount > 0 || $inProgressDaysCount > 0 || $pendingTreatmentDaysCount > 0 || $pendingDaysCount > 0);

        $avgMinutes = $workedDaysCount > 0 ? (int) round($totalWorkedMinutes / $workedDaysCount) : 0;
        $avgFormatted = $workedDaysCount > 0 ? sprintf('%02dh %02dm', intdiv($avgMinutes, 60), $avgMinutes % 60) : ($totalWorkedMinutes > 0 ? 'Parcial' : 'N/D');

        $settlementService = app(WorkTimeSettlementService::class);
        $settlementPolicy = $settlementService->resolvePolicy($employee, $periodEnd);
        $modality = $settlementPolicy->modality;
        $operatesTimeBank = $modality->operatesTimeBankLedger() || ($policy && $policy->enabled);

        $timeBankSummary = null;
        $reconciliationResult = null;
        if ($operatesTimeBank) {
            $timeBankSummary = $this->timeBankStatementService->getMonthlySummary($employee, $year, $month);
            $reconciliationResult = $this->reconciliationService->reconcile($employee, $year, $month, $timeBankSummary, $daysCalculated);
        }

        $settledMinutes = 0;
        $pendingSettlementMinutes = 0;
        if (class_exists(WorkTimeSettlement::class) && Schema::hasTable('work_time_settlements')) {
            $refPeriod = sprintf('%04d-%02d', $year, $month);
            $settlements = WorkTimeSettlement::where('employee_id', $employee->id)
                ->where(function ($q) use ($refPeriod, $year, $month) {
                    $q->where('reference_period', $refPeriod)
                        ->orWhere(function ($q2) use ($year, $month) {
                            $q2->whereYear('operation_date', $year)->whereMonth('operation_date', $month);
                        });
                })
                ->get();
            $settledMinutes = $settlements->where('status', WorkTimeSettlementStatus::Executed)->sum('minutes');
            $pendingSettlementMinutes = $settlements->where('status', WorkTimeSettlementStatus::Pending)->sum('minutes');
        }

        $notes = [
            'A diferença líquida é uma apuração puramente matemática entre previstas e trabalhadas e não representa compensação jurídica automática de créditos e débitos.',
        ];
        if ($pendingDaysCount > 0) {
            $notes[] = sprintf('Foram excluídas do total definitivo %d jornada(s) com apuração pendente.', $pendingDaysCount);
        }
        if ($uncalculableDaysCount > 0) {
            $notes[] = sprintf('%d dia(s) sem escala configurada não puderam ser calculados.', $uncalculableDaysCount);
        }

        $destinationDescription = match ($modality) {
            SettlementModality::NoBank, SettlementModality::DirectPayroll => 'Empresa sem banco de horas ativo. Horas apuradas são destinadas diretamente à folha de pagamento.',
            SettlementModality::CumulativeBank, SettlementModality::Hybrid => 'Empresa com banco de horas ativo. As diferenças da jornada são apuradas separadamente das movimentações do banco.',
            SettlementModality::MonthlyCompensation => 'Regime de compensação mensal. Valores destinados à compensação dentro do mês civil.',
            SettlementModality::TimeOff => 'Regime de compensação em folgas acordadas.',
            SettlementModality::LegacyMonthlyReset => 'Regime de fechamento mensal legado.',
        };

        $netMinutes = $positiveMinutes + $negativeMinutes;
        $status = $hasIncompleteData ? 'partial' : 'definitive';
        $statusLabel = $status === 'partial' ? 'Apuração Parcial (há jornadas pendentes)' : 'Apuração Definitiva';

        $differencesSummary = new MonthlyDifferencesSummary(
            positiveMinutes: $positiveMinutes,
            negativeMinutes: $negativeMinutes,
            netMinutes: $netMinutes,
            status: $status,
            statusLabel: $statusLabel,
            concludedDaysCount: $concludedDaysCount,
            pendingDaysCount: $pendingDaysCount,
            uncalculableDaysCount: $uncalculableDaysCount,
            settlementModality: $modality,
            operatesTimeBank: $operatesTimeBank,
            provisionalPositiveMinutes: $provisionalPositiveMinutes,
            provisionalNegativeMinutes: $provisionalNegativeMinutes,
            destinedToPayrollMinutes: $destinedToPayrollTotal,
            destinedToCompensationMinutes: $destinedToCompTotal,
            settledMinutes: $settledMinutes,
            pendingSettlementMinutes: $pendingSettlementMinutes,
            destinationDescription: $destinationDescription,
            notes: $notes,
        );

        return [
            'isClosedPeriod' => false,
            'closedPeriod' => null,
            'snapshotVersion' => null,
            'groupedEntries' => $groupedEntries,
            'daysCalculated' => $daysCalculated,
            'totalMonthFormatted' => $monthFormatted,
            'totalWorkedMinutes' => $totalWorkedMinutes,
            'workedDaysCount' => $workedDaysCount,
            'completedDaysCount' => $completedDaysCount,
            'incompleteDaysCount' => $incompleteDaysCount,
            'inProgressDaysCount' => $inProgressDaysCount,
            'pendingTreatmentDaysCount' => $pendingTreatmentDaysCount,
            'totalPunches' => $totalPunchesCount,
            'avgFormatted' => $avgFormatted,
            'avgCriteria' => $workedDaysCount > 0
                ? sprintf('Média calculada sobre %d %s com jornada concluída', $workedDaysCount, $workedDaysCount === 1 ? 'dia' : 'dias')
                : 'Aguardando fechamento de jornadas completas para cálculo de média',
            'isPartial' => $hasIncompleteData,
            'timeBankSummary' => $timeBankSummary,
            'reconciliationResult' => $reconciliationResult,
            'policy' => $policy,
            'employee' => $employee,
            'differencesSummary' => $differencesSummary,
            'operatesTimeBank' => $operatesTimeBank,
        ];
    }

    /**
     * Classifica o status do dia de forma estrita e semântica com as regras do PTRP.
     *
     * @return array{key: string, label: string, badge_class: string}
     */
    protected function determineDayStatus(Carbon $date, CalculatedJourney $journey, bool $hasPendingTreatment): array
    {
        if ($hasPendingTreatment) {
            return [
                'key' => 'pending_treatment',
                'label' => 'Aguardando tratamento',
                'badge_class' => 'bg-amber-50 text-amber-800 border-amber-200',
            ];
        }

        $punches = $journey->effectivePunches;
        $punchCount = count($punches);

        if ($punchCount === 0) {
            return [
                'key' => 'no_punches',
                'label' => 'Sem marcações',
                'badge_class' => 'bg-gray-100 text-gray-700 border-gray-200',
            ];
        }

        $isToday = $date->isToday();
        $isYesterday = $date->isYesterday();

        // Se a jornada foi concluída (pares fechados)
        if (! $journey->isIncomplete && $journey->workedMinutes > 0) {
            return [
                'key' => 'concluded',
                'label' => 'Jornada concluída',
                'badge_class' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            ];
        }

        // Se há batida sem par:
        // Caso A: Se for hoje e a última batida for uma entrada, está em andamento no expediente atual
        if ($isToday && $punchCount % 2 !== 0 && end($punches)['type'] === 'in') {
            return [
                'key' => 'in_progress',
                'label' => 'em andamento',
                'badge_class' => 'bg-amber-50 text-amber-800 border-amber-200 animate-pulse',
            ];
        }

        // Caso B: Se for ontem à noite (>=18h) e tiver menos de 16 horas da entrada, é jornada noturna em andamento
        if ($isYesterday && $punchCount % 2 !== 0 && end($punches)['type'] === 'in') {
            $lastPunchTime = Carbon::parse(end($punches)['timestamp'] ?? ($date->format('Y-m-d').' '.end($punches)['time']));
            if ($lastPunchTime->hour >= 18 && now()->diffInHours($lastPunchTime) <= 16) {
                return [
                    'key' => 'in_progress',
                    'label' => 'Jornada noturna em andamento',
                    'badge_class' => 'bg-indigo-50 text-indigo-800 border-indigo-200 animate-pulse',
                ];
            }
        }

        // Caso C: Entrada de dias anteriores sem saída correspondente não é "em andamento" — é jornada incompleta!
        return [
            'key' => 'incomplete',
            'label' => 'Jornada incompleta',
            'badge_class' => 'bg-rose-50 text-rose-800 border-rose-200',
        ];
    }

    /**
     * Carrega as marcações brutas de PunchEvent (com fallback para TimeEntry legada)
     * organizadas em objetos consistentes com coordenadas e metadados.
     *
     * @return array<string, Collection>
     */
    protected function loadRawEntriesGroupedByDate(User $user, Employee $employee, int $year, int $month): array
    {
        $punches = PunchEvent::with('receipt')
            ->where(function ($q) use ($user, $employee) {
                $q->where('employee_id', $employee->id)
                    ->orWhere('user_id', $user->id);
            })
            ->whereYear('occurred_at_local', $year)
            ->whereMonth('occurred_at_local', $month)
            ->orderBy('occurred_at_local', 'asc')
            ->get();

        if ($punches->isEmpty()) {
            $legacy = TimeEntry::where('user_id', $user->id)
                ->whereYear('timestamp', $year)
                ->whereMonth('timestamp', $month)
                ->orderBy('timestamp', 'asc')
                ->get();

            return $legacy->groupBy(fn ($e) => Carbon::parse($e->timestamp)->format('Y-m-d'))
                ->map(fn ($group) => $group->map(fn ($entry) => (object) [
                    'id' => (string) $entry->id,
                    'type' => $entry->type,
                    'timestamp' => Carbon::parse($entry->timestamp),
                    'is_manual' => (bool) $entry->is_manual,
                    'latitude' => $entry->latitude,
                    'longitude' => $entry->longitude,
                    'has_valid_location' => $this->isValidCoordinate($entry->latitude, $entry->longitude),
                    'nsr' => null,
                    'receipt' => null,
                ]))
                ->all();
        }

        return $punches->groupBy(fn ($p) => $p->occurred_at_local->format('Y-m-d'))
            ->map(fn ($group) => $group->map(fn ($punch) => (object) [
                'id' => (string) $punch->id,
                'type' => $punch->direction,
                'timestamp' => $punch->occurred_at_local,
                'is_manual' => false,
                'latitude' => $punch->latitude,
                'longitude' => $punch->longitude,
                'has_valid_location' => $this->isValidCoordinate($punch->latitude, $punch->longitude),
                'nsr' => $punch->nsr,
                'receipt' => $punch->receipt,
            ]))
            ->all();
    }

    /**
     * Valida se uma coordenada geográfica é real e não-nula/não-zerada artificialmente.
     */
    protected function isValidCoordinate(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }

        // Evita falsos positivos como Lat: 0.0000, Lng: 0.0000
        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
            return false;
        }

        return true;
    }

    /**
     * Converte batidas de snapshot imutável em objetos de entrada para exibição.
     */
    protected function formatEffectivePunchesAsEntries(array $effectivePunches, string $dateStr): Collection
    {
        return collect($effectivePunches)->map(function ($p) use ($dateStr) {
            $timestamp = isset($p['timestamp']) ? Carbon::parse($p['timestamp']) : Carbon::parse($dateStr.' '.($p['time'] ?? '00:00'));

            return (object) [
                'id' => (string) ($p['id'] ?? uniqid()),
                'type' => $p['type'] ?? 'in',
                'timestamp' => $timestamp,
                'is_manual' => ($p['source'] ?? '') === 'ptrp_manual',
                'latitude' => null,
                'longitude' => null,
                'has_valid_location' => false,
                'nsr' => $p['nsr'] ?? null,
                'receipt' => null,
            ];
        });
    }
}
