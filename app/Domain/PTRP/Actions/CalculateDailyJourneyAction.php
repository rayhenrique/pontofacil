<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\Calendar\Services\WorkCalendarService;
use App\Domain\Company\Services\CurrentCompany;
use App\Domain\LaborRules\Actions\CalculateNightWorkAction;
use App\Domain\LaborRules\DTOs\WorkInterval;
use App\Domain\PTRP\DTOs\CalculatedJourney;
use App\Domain\PTRP\DTOs\DailyJourneyPrefetch;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Policies\LaborPolicy;
use App\Domain\Settlement\DTOs\WorkTimeApuracaoResult;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Domain\WorkSchedule\Services\FlexibleShiftService;
use App\Domain\WorkSchedule\Services\ShiftMidnightService;
use App\Enums\ShiftOrigin;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\WorkScheduleModality;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\PunchEvent;
use App\Models\ShiftAssignment;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use App\Models\WorkSchedule;
use App\Models\WorkTimeSettlementPolicy;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CalculateDailyJourneyAction
{
    public function __construct(
        protected ?ShiftMidnightService $shiftMidnightService = null,
        protected ?ResolveEffectiveJourneySequenceAction $sequenceResolver = null
    ) {
        $this->shiftMidnightService ??= app(ShiftMidnightService::class);
        $this->sequenceResolver ??= app(ResolveEffectiveJourneySequenceAction::class);
    }

    /**
     * Combina fatos brutos do REP (PunchEvent) + Tratamentos Aprovados (TreatmentEvent)
     * + Escala de Trabalho e Plantões (WorkSchedule / ShiftAssignment) + Calendário Laboral (CalendarEvent)
     * + Diretrizes Legais e Perfil Normativo (LaborRuleProfile / LaborPolicy) para apurar a jornada analítica do trabalhador.
     *
     * A MARCAÇÃO ORIGINAL EM PUNCH_EVENTS NUNCA É ALTERADA.
     */
    public function execute(
        Employee $employee,
        CarbonInterface $date,
        ?WorkSchedule $schedule = null,
        ?LaborPolicy $policy = null,
        ?WorkTimeSettlementPolicy $settlementPolicy = null,
        ?Collection $prefetchedRawPunches = null,
        ?Collection $prefetchedApprovedTreatments = null,
        ?ShiftAssignment $prefetchedShift = null,
        ?DailyJourneyPrefetch $prefetch = null,
    ): CalculatedJourney {
        $establishment = $prefetch?->establishment ?? $employee->sector?->establishment ?? CurrentCompany::defaultEstablishment();
        $tz = $establishment?->resolvedTimezone() ?? config('app.timezone', 'America/Maceio');

        $dateStr = $date->format('Y-m-d');
        $localDay = Carbon::parse($dateStr, $tz);
        $dayStart = $localDay->copy()->startOfDay();
        $dayEnd = $localDay->copy()->addDay()->startOfDay();

        // Jornada futura: prevista, porém ainda não apurada (nunca déficit definitivo).
        $isFutureDay = $dayStart->gt(Carbon::today($tz));

        // 1. Resolver Plantão (ShiftAssignment) e Escala de Trabalho (WorkSchedule)
        if ($prefetch !== null && $prefetch->shiftResolved) {
            $shift = $prefetch->shift;
        } else {
            $shift = $prefetchedShift ?? ShiftAssignment::where('employee_id', $employee->id)
                ->active()
                ->where('start_at_local', '>=', $dayStart)
                ->where('start_at_local', '<', $dayEnd)
                ->first();
        }

        if ($schedule !== null) {
            $workSchedule = $schedule;
        } elseif ($prefetch !== null && $prefetch->scheduleResolved) {
            $workSchedule = $prefetch->resolvedSchedule;
        } else {
            $workSchedule = $shift?->workSchedule ?? $employee->getWorkScheduleForDate($date);
        }
        $isScheduleAuthorized = $workSchedule ? $workSchedule->isAuthorizedForRegime($employee->legal_regime) : false;

        $hasSchedule = ($shift !== null) || ($workSchedule !== null && $isScheduleAuthorized);
        $isPendingConfiguration = ! $hasSchedule;

        $workScheduleId = $shift?->work_schedule_id ?? ($isScheduleAuthorized ? $workSchedule?->id : null);
        $workScheduleCode = $shift?->workSchedule?->code ?? ($isScheduleAuthorized ? $workSchedule?->code : null);
        $shiftAssignmentId = $shift?->id;
        $shiftCode = $shift?->shift_code;

        if ($shift) {
            if ($shift->is_day_off) {
                $scheduledMinutes = 0;
                $expectedPeriods = [];
                $expectedBreakMinutes = 0;
            } else {
                $scheduledMinutes = $shift->expected_work_minutes > 0
                    ? $shift->expected_work_minutes
                    : max(0, $shift->durationInMinutes() - $shift->break_minutes);
                $expectedPeriods = [
                    [
                        'start' => $shift->start_at_local->format('H:i'),
                        'end' => $shift->end_at_local->format('H:i'),
                    ],
                ];
                $expectedBreakMinutes = $shift->break_minutes;
            }
        } elseif ($workSchedule && $isScheduleAuthorized) {
            $daySchedule = $workSchedule->getScheduleForDay($date->dayOfWeek);
            $scheduledMinutes = (int) ($daySchedule['expected_minutes'] ?? 0);
            $expectedPeriods = $daySchedule['periods'] ?? [];
            $expectedBreakMinutes = (int) ($daySchedule['break_minutes'] ?? 0);
        } else {
            $scheduledMinutes = 0;
            $expectedPeriods = [];
            $expectedBreakMinutes = 0;
        }

        // 2. Perfil de regras jurídicas aplicável (CLT, Estatutário Federal, Estadual ou Municipal)
        if ($prefetch !== null && $prefetch->ruleProfileResolved) {
            $ruleProfile = $prefetch->ruleProfile;
        } else {
            $ruleProfile = $employee->getLaborRuleProfileForDate($date);
        }
        $laborRuleProfileId = $ruleProfile?->id;
        $laborRuleProfileCode = $ruleProfile?->code;

        // 3. Política de tolerância CLT (Art. 58, § 1º)
        $laborPolicy = $policy ?? new LaborPolicy(
            $workSchedule?->tolerance_minutes ?? 5,
            $workSchedule?->daily_tolerance_minutes ?? 10
        );

        // 4. Consultar Calendário Laboral (20.18.8)
        $establishment = $prefetch?->establishment ?? $employee->sector?->establishment ?? Establishment::first();
        $calendarService = app(WorkCalendarService::class);
        $calendarDay = $calendarService->resolveDay($date, $establishment, $scheduledMinutes, $expectedPeriods, $prefetch?->calendarEvents);

        $is12x36 = ($shift?->shift_type === ShiftType::Shift12x36)
            || ($workSchedule?->modality === WorkScheduleModality::TwelveByThirtySix)
            || ($workSchedule?->modality === WorkScheduleModality::FlexibleTwelveByThirtySix)
            || ($shift !== null && $shift->isFlexible());

        if ($hasSchedule) {
            if ($is12x36 && (($shift && ! $shift->is_day_off) || ($workSchedule && $scheduledMinutes > 0))) {
                // Em escala 12x36 com jornada prevista, a escala contratual é mantida no feriado (CLT Art. 59-A)
            } else {
                $scheduledMinutes = $calendarDay->expectedMinutes;
            }
        }
        $calendarSnapshot = $calendarDay->toSnapshotArray();

        $treatmentNotes = [];
        $toleratedMinutes = 0;

        if ($isPendingConfiguration) {
            $treatmentNotes[] = 'Ausência de escala de trabalho válida ou autorizada para a data. Apuração financeira pendente de parametrização.';
        }

        // Anotar eventos de calendário aplicados
        foreach ($calendarDay->appliedEvents as $event) {
            $treatmentNotes[] = sprintf(
                '%s: %s (%s — %s).',
                $event->type->label(),
                $event->name,
                $event->scope->label(),
                $event->work_behavior->label()
            );
        }

        // 5. Obter marcações brutas do REP (somente leitura)
        $rawPunches = $prefetchedRawPunches ?? PunchEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('user_id', $employee->user_id);
        })
            ->where('occurred_at_local', '>=', $dayStart)
            ->where('occurred_at_local', '<', $dayEnd)
            ->orderBy('occurred_at_local', 'asc')
            ->get();

        // 6. Obter eventos de tratamento aprovados (PTRP)
        $approvedTreatments = $prefetchedApprovedTreatments ?? TreatmentEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->where('status', TreatmentEventStatus::Approved)
            ->where('effective_at', '>=', $dayStart)
            ->where('effective_at', '<', $dayEnd)
            ->get();

        $disregardedPunchIds = $approvedTreatments
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->filter(fn ($t) => ($t->reference_source ?? 'punch_event') === 'punch_event' && ! empty($t->reference_punch_id))
            ->pluck('reference_punch_id')
            ->filter()
            ->all();

        $disregardedTimeEntryIds = $approvedTreatments
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->filter(fn ($t) => $t->reference_source === 'time_entry' || ! empty($t->reference_time_entry_id))
            ->map(fn ($t) => $t->reference_time_entry_id ?? $t->reference_punch_id)
            ->filter()
            ->all();

        $justifiedAbsence = $approvedTreatments
            ->where('type', TreatmentEventType::AbsenceJustified)
            ->first();

        // 7. Construir lista de batidas efetivas (Efetivo = Bruto - Desconsideradas + Inclusões Manuais)
        $legacyEntriesResolved = ($prefetch !== null && $prefetch->legacyResolved)
            ? $prefetch->legacyEntries
            : null;

        $effectivePunches = $this->sequenceResolver->execute(
            employee: $employee,
            date: $localDay,
            rawPunches: $rawPunches,
            legacyEntries: $legacyEntriesResolved,
            approvedTreatments: $approvedTreatments,
            coveringShift: $shift,
            establishment: $establishment
        );

        // Anotar justificativas fiscais e tratamentos de ponto no espelho
        foreach ($rawPunches as $punch) {
            if (in_array((string) $punch->id, $disregardedPunchIds, true)) {
                $treatmentNotes[] = sprintf('Marcação #%s (%s) desconsiderada por justificativa aprovada.', $punch->nsr, $punch->occurred_at_local->format('H:i'));
            }
        }
        foreach ($approvedTreatments->where('type', TreatmentEventType::ManualPunchAdded) as $manual) {
            $treatmentNotes[] = sprintf('Batida manual inserida às %s (%s).', $manual->effective_at->format('H:i'), $manual->reason_text);
        }

        // 7.1. Se for plantonista flexível 12x36, executar reconhecimento automático do turno
        $isFlexibleSchedule = ($workSchedule?->modality === WorkScheduleModality::FlexibleTwelveByThirtySix)
            || ($shift !== null && $shift->isFlexible());

        $identifiedShiftTurn = null;
        $identifiedShiftTurnLabel = null;

        if ($isFlexibleSchedule) {
            $flexService = app(FlexibleShiftService::class);
            $classifiedTurn = $flexService->classifyTurn($effectivePunches, $workSchedule, $tz);
            $identifiedShiftTurn = $classifiedTurn;

            if ($classifiedTurn === FlexibleShiftService::TURN_DAY) {
                $identifiedShiftTurnLabel = 'Diurno';
                $treatmentNotes[] = 'Turno identificado: Diurno (07:00 às 19:00).';
                if ($shift) {
                    $flexService->syncTurnToShift($shift, FlexibleShiftService::TURN_DAY, $localDay, $workSchedule);
                    $shift->refresh();
                    $scheduledMinutes = 720 - $shift->break_minutes;
                } else {
                    $scheduledMinutes = 720 - (int) ($workSchedule?->cycle_data['break_minutes'] ?? 60);
                }
            } elseif ($classifiedTurn === FlexibleShiftService::TURN_NIGHT) {
                $identifiedShiftTurnLabel = 'Noturno';
                $treatmentNotes[] = 'Turno identificado: Noturno (19:00 às 07:00 do dia seguinte).';
                if ($shift) {
                    $flexService->syncTurnToShift($shift, FlexibleShiftService::TURN_NIGHT, $localDay, $workSchedule);
                    $shift->refresh();
                    $scheduledMinutes = 720 - $shift->break_minutes;
                } else {
                    $nightStartStr = $workSchedule?->cycle_data['night_start_time'] ?? '19:00';
                    $nightEndStr = $workSchedule?->cycle_data['night_end_time'] ?? '07:00';
                    [$nSH, $nSM] = explode(':', $nightStartStr);
                    [$nEH, $nEM] = explode(':', $nightEndStr);

                    $startLocal = $localDay->copy()->setTime((int) $nSH, (int) $nSM, 0);
                    $endLocal = $localDay->copy()->addDay()->setTime((int) $nEH, (int) $nEM, 0);
                    $breakMin = (int) ($workSchedule?->cycle_data['break_minutes'] ?? 60);

                    $activeAssignment = $employee->workScheduleAssignments()
                        ->where('effective_from', '<=', $localDay->format('Y-m-d'))
                        ->where(function ($q) use ($localDay) {
                            $q->whereNull('effective_until')
                                ->orWhere('effective_until', '>=', $localDay->format('Y-m-d'));
                        })
                        ->first();

                    $shift = ShiftAssignment::create([
                        'shift_code' => (string) Str::uuid(),
                        'employee_id' => $employee->id,
                        'work_schedule_assignment_id' => $activeAssignment?->id,
                        'work_schedule_id' => $workSchedule?->id,
                        'start_at_utc' => $startLocal->copy()->setTimezone('UTC'),
                        'end_at_utc' => $endLocal->copy()->setTimezone('UTC'),
                        'start_at_local' => $startLocal,
                        'end_at_local' => $endLocal,
                        'timezone' => $tz,
                        'break_minutes' => $breakMin,
                        'expected_work_minutes' => 720 - $breakMin,
                        'shift_type' => ShiftType::Shift12x36,
                        'origin' => ShiftOrigin::GeneratedCycle,
                        'status' => ShiftStatus::Scheduled,
                        'is_day_off' => false,
                        'is_night_shift' => true,
                        'crosses_midnight' => true,
                        'recognized_turn' => FlexibleShiftService::TURN_NIGHT,
                        'reason' => 'Reconhecimento automático de plantão flexível noturno',
                        'notes' => 'Plantão Flexível 12×36 — Turno Noturno reconhecido automaticamente pelas marcações.',
                    ]);
                    $scheduledMinutes = 720 - $breakMin;
                    $shiftAssignmentId = $shift->id;
                    $shiftCode = $shift->shift_code;
                }
            } elseif ($classifiedTurn === FlexibleShiftService::TURN_PENDING) {
                $identifiedShiftTurnLabel = 'Pendente de Classificação';
                $treatmentNotes[] = 'Plantonista Flexível: marcações fora das janelas de referência de turno — jornada pendente de classificação para conferência administrativa.';
                if ($shift) {
                    $flexService->syncTurnToShift($shift, FlexibleShiftService::TURN_PENDING, $localDay, $workSchedule);
                }
            } elseif ($classifiedTurn === FlexibleShiftService::TURN_UNDEFINED) {
                $identifiedShiftTurnLabel = 'A Definir';
            }
        }

        $isCurrentShiftMidnight = $this->shiftMidnightService->isAuthorizedCrossMidnightShift($shift, $employee);

        // Jornada em andamento: hoje com entrada sem saída, ou plantão autorizado em andamento.
        $isInProgressDay = false;
        $punchCount = count($effectivePunches);
        if ($punchCount > 0) {
            $lastPunch = end($effectivePunches);
            $lastType = $lastPunch['type'] ?? $lastPunch['direction'] ?? null;
            if ($lastType === 'in' || $lastType === 'clock_in') {
                if ($localDay->isToday() && $punchCount % 2 !== 0) {
                    $isInProgressDay = true;
                } elseif ($localDay->isYesterday() && $punchCount % 2 !== 0 && $isCurrentShiftMidnight && $shift !== null) {
                    $lastEntryTime = Carbon::parse($lastPunch['timestamp'] ?? ($dateStr.' '.$lastPunch['time']));
                    if (now()->diffInHours($lastEntryTime) <= 24 && now()->lte($shift->end_at_local->copy()->addHours(2))) {
                        $isInProgressDay = true;
                    }
                }
            }
        }

        if ($isInProgressDay) {
            $treatmentNotes[] = 'Jornada em andamento — apuração parcial do dia.';
        }

        // 8. Verificar se marcações do início do dia pertencem a plantão noturno autorizado iniciado na véspera
        $prevDate = $localDay->copy()->subDay();
        $prevDateStr = $prevDate->format('Y-m-d');
        $prevDayStart = $prevDate->copy()->startOfDay();
        $prevDayEnd = $localDay->copy()->startOfDay();

        if ($prefetch !== null && $prefetch->neighborsResolved) {
            $prevShift = $prefetch->prevShift;
        } else {
            $prevShifts = ShiftAssignment::where('employee_id', $employee->id)
                ->active()
                ->where('start_at_local', '>=', $prevDayStart)
                ->where('start_at_local', '<', $prevDayEnd)
                ->orderBy('start_at_local', 'desc')
                ->get();

            $authorizedPrevShifts = $prevShifts->filter(
                fn ($s) => $this->shiftMidnightService->isAuthorizedCrossMidnightShift($s, $employee)
            );

            if ($authorizedPrevShifts->count() > 1) {
                $treatmentNotes[] = 'Alerta: múltiplos plantões autorizados com virada de meia-noite detectados na véspera — pendência para conferência do RH.';
                $prevShift = null;
            } else {
                $prevShift = $authorizedPrevShifts->first();
            }
        }

        $isPrevShiftMidnight = $prevShift && $this->shiftMidnightService->isAuthorizedCrossMidnightShift($prevShift, $employee);

        // Confirmar se o plantão da véspera tinha de fato uma sequência em aberto (entrada sem saída correspondente antes da 00h)
        $prevHadOpenSequence = false;
        if ($isPrevShiftMidnight) {
            if ($prefetch !== null && $prefetch->neighborsResolved) {
                $prevPunchesRaw = $prefetch->prevDayPunches;
            } else {
                $prevPunchesRaw = PunchEvent::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                        ->orWhere('user_id', $employee->user_id);
                })
                    ->where('occurred_at_local', '>=', $prevDayStart)
                    ->where('occurred_at_local', '<', $prevDayEnd)
                    ->orderBy('occurred_at_local', 'asc')
                    ->get();

                if ($prevPunchesRaw->isEmpty() && $employee->user_id) {
                    $prevPunchesRaw = TimeEntry::where('user_id', $employee->user_id)
                        ->where('timestamp', '>=', $prevDayStart)
                        ->where('timestamp', '<', $prevDayEnd)
                        ->orderBy('timestamp', 'asc')
                        ->get();
                }
            }

            // Tratamentos aprovados da véspera
            $prevTreatments = TreatmentEvent::where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                    ->orWhere('employment_id', $employee->id);
            })
                ->where('status', TreatmentEventStatus::Approved)
                ->where('effective_at', '>=', $prevDayStart)
                ->where('effective_at', '<', $prevDayEnd)
                ->get();

            $prevDisregardedIds = $prevTreatments
                ->where('type', TreatmentEventType::PunchDisregarded)
                ->map(fn ($t) => $t->reference_source === 'time_entry' ? ($t->reference_time_entry_id ?? $t->reference_punch_id) : $t->reference_punch_id)
                ->filter()
                ->all();

            $prevEffectiveCount = 0;
            $prevLastDir = null;
            foreach ($prevPunchesRaw as $pp) {
                if (in_array((string) $pp->id, $prevDisregardedIds, true) || in_array((int) $pp->id, $prevDisregardedIds, true)) {
                    continue;
                }
                $prevEffectiveCount++;
                $prevLastDir = $pp->direction ?? (match ($pp->type ?? '') {
                    'clock_out', 'out' => 'out',
                    default => 'in',
                });
            }

            foreach ($prevTreatments->where('type', TreatmentEventType::ManualPunchAdded) as $mp) {
                $prevEffectiveCount++;
                $prevLastDir = $mp->new_value_json['direction'] ?? 'in';
            }

            $prevHadOpenSequence = ($prevEffectiveCount > 0);
        }

        if ($isPrevShiftMidnight && $prevHadOpenSequence && ! empty($effectivePunches)) {
            $prevShiftEndWithGrace = $prevShift->end_at_local->copy()->addHours(3);
            $currentShiftDir = $prevLastDir;

            foreach ($effectivePunches as &$punchRef) {
                $pTimestamp = $punchRef['timestamp'] instanceof CarbonInterface
                    ? $punchRef['timestamp']
                    : Carbon::parse($punchRef['timestamp']);

                $dir = $punchRef['direction'] ?? $punchRef['type'] ?? 'in';
                if ($dir === 'clock_out') {
                    $dir = 'out';
                } elseif ($dir === 'clock_in') {
                    $dir = 'in';
                }

                // Se passou do limite com tolerância operacional, interrompe
                if ($pTimestamp->gt($prevShiftEndWithGrace)) {
                    break;
                }

                // Se já estiver além do término previsto da escala (janela de tolerância operacional):
                if ($pTimestamp->gt($prevShift->end_at_local)) {
                    // Se a sequência já estava fechada antes do término, não associa batida após o fim previsto
                    if ($currentShiftDir === 'out') {
                        break;
                    }
                    // Se for entrada na tolerância pós-término, nunca transforma em saída de ontem!
                    if ($dir === 'in') {
                        $treatmentNotes[] = sprintf(
                            'Plantão anterior (iniciado em %s) sem marcação de saída registrada; marcação de entrada das %s preservada na data atual.',
                            $prevDateStr,
                            $punchRef['time']
                        );
                        break;
                    }
                    // Se for saída e a sequência estava aberta, é o encerramento com prorrogação/tolerância
                    $punchRef['is_previous_day_exit'] = true;
                    $treatmentNotes[] = sprintf(
                        'Marcação das %s vinculada ao plantão noturno iniciado na véspera (%s).',
                        $punchRef['time'],
                        $prevDateStr
                    );
                    $currentShiftDir = 'out';
                    break;
                }

                // Instante dentro da escala do plantão (<= end_at_local):
                if ($currentShiftDir === 'out') {
                    // Sequência fechada (ex: saída para ceia/intervalo antes da meia-noite):
                    // Uma batida de entrada dentro do plantão é retorno do intervalo
                    if ($dir === 'in') {
                        $punchRef['is_previous_day_exit'] = true;
                        $treatmentNotes[] = sprintf(
                            'Marcação das %s vinculada ao plantão noturno iniciado na véspera (%s).',
                            $punchRef['time'],
                            $prevDateStr
                        );
                        $currentShiftDir = 'in';
                    } else {
                        break;
                    }
                } else {
                    // Sequência aberta ($currentShiftDir === 'in'):
                    // Uma saída dentro do plantão é saída do plantão ou início de intervalo
                    if ($dir === 'out') {
                        $punchRef['is_previous_day_exit'] = true;
                        $treatmentNotes[] = sprintf(
                            'Marcação das %s vinculada ao plantão noturno iniciado na véspera (%s).',
                            $punchRef['time'],
                            $prevDateStr
                        );
                        $currentShiftDir = 'out';
                    } else {
                        break;
                    }
                }
            }
            unset($punchRef);
        }

        // 9. Se hoje for um plantão autorizado que atravessa a meia-noite, buscar continuidade no dia seguinte
        $dayEffectiveBeforeNext = array_values(array_filter($effectivePunches, fn ($p) => empty($p['is_previous_day_exit'])));
        $dayEffectiveCount = count($dayEffectiveBeforeNext);
        $dayLastDir = null;
        if ($dayEffectiveCount > 0) {
            $lastP = $dayEffectiveBeforeNext[$dayEffectiveCount - 1];
            $dayLastDir = $lastP['direction'] ?? $lastP['type'] ?? 'in';
            if ($dayLastDir === 'clock_out') {
                $dayLastDir = 'out';
            } elseif ($dayLastDir === 'clock_in') {
                $dayLastDir = 'in';
            }
        }

        if ($isCurrentShiftMidnight && $shift !== null && $dayEffectiveCount > 0) {
            $nextDate = $localDay->copy()->addDay();
            $nextDateStr = $nextDate->format('Y-m-d');
            $nextDayStart = $dayEnd->copy();
            $nextDayEnd = $nextDayStart->copy()->addDay();
            $shiftEndWithGrace = $shift->end_at_local->copy()->addHours(3);

            if ($prefetch !== null && $prefetch->neighborsResolved && $prefetch->nextDayPunches->isNotEmpty()) {
                $nextPunches = $prefetch->nextDayPunches;
            } else {
                $nextPunches = PunchEvent::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                        ->orWhere('user_id', $employee->user_id);
                })
                    ->where('occurred_at_local', '>=', $nextDayStart)
                    ->where('occurred_at_local', '<', $nextDayEnd)
                    ->orderBy('occurred_at_local', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                if ($nextPunches->isEmpty() && $employee->user_id) {
                    $nextPunches = TimeEntry::where('user_id', $employee->user_id)
                        ->where('timestamp', '>=', $nextDayStart)
                        ->where('timestamp', '<', $nextDayEnd)
                        ->orderBy('timestamp', 'asc')
                        ->get();
                }
            }

            $nextTreatments = TreatmentEvent::where(function ($q) use ($employee) {
                $q->where('employee_id', $employee->id)
                    ->orWhere('employment_id', $employee->id);
            })
                ->where('status', TreatmentEventStatus::Approved)
                ->where('effective_at', '>=', $nextDayStart)
                ->where('effective_at', '<=', $shiftEndWithGrace)
                ->orderBy('effective_at', 'asc')
                ->get();

            $nextDisregardedIds = $nextTreatments
                ->where('type', TreatmentEventType::PunchDisregarded)
                ->map(fn ($t) => $t->reference_source === 'time_entry' ? ($t->reference_time_entry_id ?? $t->reference_punch_id) : $t->reference_punch_id)
                ->filter()
                ->all();

            $allNextCandidates = [];
            foreach ($nextPunches as $np) {
                $npId = (string) $np->id;
                if (in_array($npId, $nextDisregardedIds, true) || (ctype_digit($npId) && in_array((int) $npId, $nextDisregardedIds, true))) {
                    continue;
                }

                $npTime = Carbon::parse($np->occurred_at_local ?? $np->timestamp);
                $dir = $np->direction ?? (match ($np->type ?? '') {
                    'clock_out', 'out' => 'out',
                    'clock_in', 'in' => 'in',
                    default => 'out',
                });

                $allNextCandidates[] = [
                    'id' => $npId,
                    'nsr' => $np->nsr ?? 0,
                    'time' => $npTime->format('H:i'),
                    'timestamp' => $npTime,
                    'type' => $dir,
                    'direction' => $dir,
                    'original_direction' => $dir,
                    'effective_direction' => $dir,
                    'source' => 'rep_p_next_day_shift',
                    'is_next_day_exit' => true,
                ];
            }

            foreach ($nextTreatments->where('type', TreatmentEventType::ManualPunchAdded) as $manualNext) {
                $dir = $manualNext->new_value_json['direction'] ?? 'manual';
                if ($dir === 'clock_out') {
                    $dir = 'out';
                } elseif ($dir === 'clock_in') {
                    $dir = 'in';
                }

                $allNextCandidates[] = [
                    'id' => $manualNext->id,
                    'nsr' => 0,
                    'time' => $manualNext->effective_at->format('H:i'),
                    'timestamp' => $manualNext->effective_at,
                    'type' => $dir,
                    'direction' => $dir,
                    'original_direction' => $dir,
                    'effective_direction' => $dir,
                    'source' => 'ptrp_manual_next_day_shift',
                    'reason' => $manualNext->reason_text,
                    'is_next_day_exit' => true,
                ];
            }

            usort($allNextCandidates, function ($a, $b) {
                $tA = $a['timestamp'] instanceof CarbonInterface ? $a['timestamp']->getTimestamp() : Carbon::parse($a['timestamp'])->getTimestamp();
                $tB = $b['timestamp'] instanceof CarbonInterface ? $b['timestamp']->getTimestamp() : Carbon::parse($b['timestamp'])->getTimestamp();
                if ($tA === $tB) {
                    return ((int) ($a['nsr'] ?? 0)) <=> ((int) ($b['nsr'] ?? 0));
                }

                return $tA <=> $tB;
            });

            $collectedNextPunches = [];
            $currentShiftDir = $dayLastDir;

            foreach ($allNextCandidates as $candidate) {
                $npTime = $candidate['timestamp'];
                $dir = $candidate['direction'];

                if ($npTime->gt($shiftEndWithGrace)) {
                    break;
                }

                if ($npTime->gt($shift->end_at_local)) {
                    if ($currentShiftDir === 'out') {
                        break;
                    }
                    if ($dir === 'in') {
                        $treatmentNotes[] = sprintf(
                            'Plantão com saída pendente / não registrada; nova entrada registrada no dia seguinte (%s às %s).',
                            $nextDateStr,
                            $npTime->format('H:i')
                        );
                        break;
                    }
                    $collectedNextPunches[] = $candidate;
                    $currentShiftDir = 'out';
                    break;
                }

                // Dentro da escala (<= end_at_local):
                if ($currentShiftDir === 'out') {
                    if ($dir === 'in') {
                        $collectedNextPunches[] = $candidate;
                        $currentShiftDir = 'in';
                    } else {
                        break;
                    }
                } else {
                    if ($dir === 'out') {
                        $collectedNextPunches[] = $candidate;
                        $currentShiftDir = 'out';
                    } else {
                        break;
                    }
                }
            }

            if (! empty($collectedNextPunches)) {
                foreach ($collectedNextPunches as $cp) {
                    $effectivePunches[] = $cp;
                }

                $treatmentNotes[] = sprintf(
                    'Plantão noturno com continuidade no dia seguinte até %s (%s).',
                    end($collectedNextPunches)['time'],
                    $nextDateStr
                );
            }
        }

        // 10. Pareamento Direcional e Cálculo de Minutos Trabalhados e Intervalos
        $punchesToCalculate = array_values(array_filter($effectivePunches, fn ($p) => empty($p['is_previous_day_exit'])));

        // Ordenar cronologicamente por timestamp real
        usort($punchesToCalculate, function ($a, $b) {
            $tA = $a['timestamp'] instanceof CarbonInterface ? $a['timestamp']->getTimestamp() : Carbon::parse($a['timestamp'])->getTimestamp();
            $tB = $b['timestamp'] instanceof CarbonInterface ? $b['timestamp']->getTimestamp() : Carbon::parse($b['timestamp'])->getTimestamp();
            if ($tA === $tB) {
                return ((int) ($a['nsr'] ?? 0)) <=> ((int) ($b['nsr'] ?? 0));
            }

            return $tA <=> $tB;
        });

        // Recalcular sentidos efetivos finais da jornada após segregação de plantão anterior e continuidade
        $this->sequenceResolver->recalculateEffectiveDirections($punchesToCalculate);

        // Sincronizar de volta para a coleção $effectivePunches mantendo as marcações auditáveis
        $punchesById = collect($punchesToCalculate)->keyBy('id');
        foreach ($effectivePunches as &$ep) {
            if (isset($punchesById[$ep['id']])) {
                $calc = $punchesById[$ep['id']];
                $ep['effective_direction'] = $calc['effective_direction'] ?? ($ep['direction'] ?? 'in');
                $ep['is_direction_reclassified'] = $calc['is_direction_reclassified'] ?? false;
                $ep['reclassification_reason'] = $calc['reclassification_reason'] ?? null;
                $ep['type'] = $calc['effective_direction'] ?? ($ep['type'] ?? 'in');
                $ep['direction'] = $calc['effective_direction'] ?? ($ep['direction'] ?? 'in');
                $ep['is_duplicate'] = $calc['is_duplicate'] ?? false;
            }
        }
        unset($ep);

        $workedMinutes = 0;
        $breakMinutes = 0;
        $workedIntervals = [];
        $breakIntervals = [];
        $isIncomplete = false;
        $hasMissingExit = false;
        $currentEntry = null;
        $lastExitTime = null;

        foreach ($punchesToCalculate as $p) {
            if (! empty($p['is_duplicate'])) {
                $treatmentNotes[] = sprintf('Marcação repetida às %s em curto intervalo preservada para fins fiscais e desconsiderada do cômputo.', $p['time']);

                continue;
            }

            $dir = $p['effective_direction'] ?? $p['direction'] ?? $p['type'] ?? 'punch';
            if ($dir === 'clock_in') {
                $dir = 'in';
            } elseif ($dir === 'clock_out') {
                $dir = 'out';
            }

            $pTime = $p['timestamp'] instanceof CarbonInterface
                ? $p['timestamp']
                : Carbon::parse($p['timestamp'] ?? ($dateStr.' '.$p['time']));

            if ($dir === 'in') {
                if ($currentEntry !== null) {
                    $isIncomplete = true;
                    $hasMissingExit = true;
                    $treatmentNotes[] = sprintf('Sequência ambígua: entrada às %s sem saída anterior (nova entrada às %s).', $currentEntry['time'], $p['time']);
                    $currentEntry = ['punch' => $p, 'time' => $p['time'], 'time_obj' => $pTime];
                } else {
                    $currentEntry = ['punch' => $p, 'time' => $p['time'], 'time_obj' => $pTime];
                    if ($lastExitTime !== null && $pTime->greaterThanOrEqualTo($lastExitTime)) {
                        $brk = $lastExitTime->diffInMinutes($pTime);
                        $breakMinutes += $brk;
                        $breakIntervals[] = new WorkInterval($lastExitTime, $pTime, 'break', true);
                    }
                }
            } elseif ($dir === 'out') {
                if ($currentEntry !== null) {
                    $entryTime = $currentEntry['time_obj'];
                    if ($pTime->greaterThanOrEqualTo($entryTime)) {
                        $dur = $entryTime->diffInMinutes($pTime);
                        $workedMinutes += $dur;
                        $workedIntervals[] = new WorkInterval($entryTime, $pTime, 'worked');
                        $lastExitTime = $pTime;
                    }
                    $currentEntry = null;
                } else {
                    $isIncomplete = true;
                    $treatmentNotes[] = sprintf('Marcação de saída às %s sem entrada correspondente registrada.', $p['time']);
                }
            } else {
                // Fallback para batidas legadas onde direção não foi especificada
                if ($currentEntry === null) {
                    $currentEntry = ['punch' => $p, 'time' => $p['time'], 'time_obj' => $pTime];
                    if ($lastExitTime !== null && $pTime->greaterThanOrEqualTo($lastExitTime)) {
                        $brk = $lastExitTime->diffInMinutes($pTime);
                        $breakMinutes += $brk;
                        $breakIntervals[] = new WorkInterval($lastExitTime, $pTime, 'break', true);
                    }
                } else {
                    $entryTime = $currentEntry['time_obj'];
                    if ($pTime->greaterThanOrEqualTo($entryTime)) {
                        $dur = $entryTime->diffInMinutes($pTime);
                        $workedMinutes += $dur;
                        $workedIntervals[] = new WorkInterval($entryTime, $pTime, 'worked');
                        $lastExitTime = $pTime;
                    }
                    $currentEntry = null;
                }
            }
        }

        if ($currentEntry !== null) {
            $isIncomplete = true;
            $hasMissingExit = true;
            $treatmentNotes[] = sprintf('Marcação de entrada às %s em aberto (sem registro de saída).', $currentEntry['time']);
        }

        if (count($punchesToCalculate) % 2 !== 0) {
            $isIncomplete = true;
        }

        if ($hasMissingExit && ! $isInProgressDay) {
            $treatmentNotes[] = 'Jornada incompleta — saída não registrada. Solicite correção.';
        }

        $treatmentNotes = array_values(array_unique($treatmentNotes));

        $is12x36 = ($shift?->shift_type === ShiftType::Shift12x36)
            || ($workSchedule?->modality === WorkScheduleModality::TwelveByThirtySix)
            || ($workSchedule?->modality === WorkScheduleModality::FlexibleTwelveByThirtySix)
            || ($shift !== null && $shift->isFlexible());

        // 10.1. Controle de descanso de 36 horas entre plantões (CLT e Portaria 671)
        if ($is12x36 && count($punchesToCalculate) > 0) {
            $firstTodayPunch = $punchesToCalculate[0];
            $todayEntryTime = $firstTodayPunch['timestamp'] instanceof CarbonInterface
                ? $firstTodayPunch['timestamp']
                : Carbon::parse($firstTodayPunch['timestamp'] ?? ($dateStr.' '.$firstTodayPunch['time']), $tz);

            // Localizar o último plantão anterior do colaborador
            $lastPriorShift = ShiftAssignment::where('employee_id', $employee->id)
                ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::Completed])
                ->where('is_day_off', false)
                ->where('end_at_local', '<', $todayEntryTime)
                ->where('id', '!=', $shift?->id ?? 0)
                ->orderBy('end_at_local', 'desc')
                ->first();

            if ($lastPriorShift) {
                $restHours = round($lastPriorShift->end_at_local->diffInMinutes($todayEntryTime, false) / 60, 2);

                if ($restHours < 0) {
                    $treatmentNotes[] = sprintf(
                        'Alerta de conflito: sobreposição com o plantão anterior encerrado às %s.',
                        $lastPriorShift->end_at_local->format('d/m/Y H:i')
                    );
                } elseif ($restHours < 36) {
                    $treatmentNotes[] = sprintf(
                        'Alerta de descanso interjornada: intervalo de %sh apurado desde o término do plantão anterior (mínimo de 36h exigido pelo ciclo 12x36).',
                        $restHours
                    );
                }
            }
        }

        // 11. Apuração comparativa com a escala e aplicação da tolerância legal (Art. 58 CLT)
        $ordinaryMinutes = 0;
        $overtimeMinutes = 0;
        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;
        $absenceMinutes = 0;
        $missingBreakMinutes = 0;
        $holidayMinutes = 0;
        $requiresCompensation = false;

        if ($expectedBreakMinutes > 0 && $breakMinutes > 0 && $breakMinutes < $expectedBreakMinutes) {
            $missingBreakMinutes = $expectedBreakMinutes - $breakMinutes;
        }

        if ($isFutureDay) {
            // Jornada futura da competência aberta: prevista, mas ainda não apurada.
            // Nunca classificada como déficit, falta ou extra definitivos.
            $ordinaryMinutes = 0;
            $overtimeMinutes = 0;
            $lateMinutes = 0;
            $earlyLeaveMinutes = 0;
            $absenceMinutes = 0;
            $holidayMinutes = 0;
            $treatmentNotes[] = 'Jornada futura — ainda não apurada.';
        } elseif (! $hasSchedule) {
            // Ausência de escala configurada: preservar batidas, mas NÃO inventar horas previstas ou extras
            $ordinaryMinutes = 0;
            $overtimeMinutes = 0;
            $lateMinutes = 0;
            $earlyLeaveMinutes = 0;
            $absenceMinutes = 0;
            $holidayMinutes = 0;
        } elseif ($is12x36 && $calendarDay->isHoliday && $workedMinutes > 0) {
            // CLT Art. 59-A, parágrafo único: feriados em regime 12x36 são compensados pelo descanso das 36 horas
            $ordinaryMinutes = min($workedMinutes, $scheduledMinutes);
            $overtimeMinutes = max(0, $workedMinutes - $scheduledMinutes);
            $holidayMinutes = 0;
            $requiresCompensation = true;
            $treatmentNotes[] = 'Feriado trabalhado em escala 12x36: compensado pela folga de 36 horas (CLT, Art. 59-A, parágrafo único).';
        } elseif ($calendarDay->isHoliday && ! $calendarDay->expectedWork) {
            // Feriado civil regular com jornada suspensa
            if ($workedMinutes === 0) {
                $absenceMinutes = 0;
                $ordinaryMinutes = 0;
            } else {
                $holidayMinutes = $workedMinutes;
                $ordinaryMinutes = 0;
                $overtimeMinutes = 0;
            }
        } elseif (! $calendarDay->expectedWork && ($calendarDay->isOptionalDay || ! empty($calendarDay->appliedEvents))) {
            // Ponto facultativo ou recesso sem expediente
            if ($workedMinutes === 0) {
                $absenceMinutes = 0;
                $ordinaryMinutes = 0;
            } else {
                $holidayMinutes = $workedMinutes;
                $ordinaryMinutes = 0;
                $overtimeMinutes = 0;
            }
        } elseif ($scheduledMinutes > 0) {
            // Dia com expediente previsto
            if ($workedMinutes === 0) {
                if ($justifiedAbsence) {
                    $absenceMinutes = 0;
                    $treatmentNotes[] = 'Ausência integral abonada/justificada: '.$justifiedAbsence->reason_text;
                } elseif ($isFlexibleSchedule && ($identifiedShiftTurn === FlexibleShiftService::TURN_UNDEFINED || $identifiedShiftTurn === null)) {
                    $absenceMinutes = 0;
                    $scheduledMinutes = 0;
                    $treatmentNotes[] = 'Plantonista Flexível: sem marcações no dia previsto pelo ciclo — jornada pendente de conferência administrativa (sem lançamento de falta presumida).';
                } else {
                    $absenceMinutes = $scheduledMinutes;
                }
            } else {
                $netDifference = $workedMinutes - $scheduledMinutes;
                $toleratedMinutes = 0;

                if ($netDifference > 0) {
                    $toleratedOvertime = $laborPolicy->applyPunchTolerance($netDifference);
                    if ($toleratedOvertime === 0) {
                        $ordinaryMinutes = $workedMinutes;
                        $overtimeMinutes = 0;
                        $toleratedMinutes = $netDifference;
                    } else {
                        $ordinaryMinutes = $scheduledMinutes;
                        $overtimeMinutes = $netDifference;
                    }
                } elseif ($netDifference < 0) {
                    $deficit = abs($netDifference);
                    $toleratedDeficit = $laborPolicy->applyPunchTolerance($deficit);
                    if ($toleratedDeficit === 0) {
                        $ordinaryMinutes = $scheduledMinutes;
                        $lateMinutes = 0;
                        $toleratedMinutes = $deficit;
                    } else {
                        $ordinaryMinutes = $workedMinutes;
                        $lateMinutes = $deficit;
                    }
                } else {
                    $ordinaryMinutes = $scheduledMinutes;
                }
            }
        } else {
            // Dia sem expediente previsto (DSR/Folga da escala)
            $overtimeMinutes = $workedMinutes;
            $ordinaryMinutes = 0;
        }

        // 12. Apuração Analítica, Classificação e Destinação de Horas (WorkTimeSettlementService)
        // Formaliza a separação dos quatro conceitos do domínio:
        // 1. Apuração (fatos analíticos de previstas, trabalhadas e saldo)
        // 2. Classificação (tolerado, justificável, extraordinário, déficit, pendente)
        // 3. Destinação (banco acumulativo, compensação mensal, folha, folga, pendente)
        $settlementService = app(WorkTimeSettlementService::class);
        $resolvedSettlementPolicy = $settlementPolicy ?? $settlementService->resolvePolicy($employee, $date);

        $apuracaoResult = new WorkTimeApuracaoResult(
            date: $dateStr,
            scheduledMinutes: $scheduledMinutes,
            workedMinutes: $workedMinutes,
            grossDifferenceMinutes: $workedMinutes - $scheduledMinutes,
            grossOvertimeMinutes: $hasSchedule ? $overtimeMinutes : $workedMinutes,
            grossLateMinutes: $lateMinutes,
            grossEarlyLeaveMinutes: $earlyLeaveMinutes,
            grossAbsenceMinutes: $absenceMinutes,
            breakMinutes: $breakMinutes,
            missingBreakMinutes: $missingBreakMinutes,
            physicalNightMinutes: 0,
            legalNightEquivalentMinutes: 0,
            hasSchedule: $hasSchedule,
            isDayOff: $scheduledMinutes === 0,
            isHoliday: $calendarDay->isHoliday,
        );

        $classificacaoResult = $settlementService->classify(
            employee: $employee,
            date: $date,
            apuracao: $apuracaoResult,
            policy: $resolvedSettlementPolicy,
            ruleProfile: $ruleProfile,
            laborPolicy: $laborPolicy,
        );

        $destinacaoResult = $settlementService->destine(
            classificacao: $classificacaoResult,
            policy: $resolvedSettlementPolicy,
        );

        $settlementSummary = $settlementService->summarize(
            employee: $employee,
            date: $date,
            apuracao: $apuracaoResult,
            classificacao: $classificacaoResult,
            destinacao: $destinacaoResult,
            policy: $resolvedSettlementPolicy,
        );

        $compensableOvertimeMinutes = $classificacaoResult->compensableOvertimeMinutes;
        $nonCompensableOvertimeMinutes = $destinacaoResult->destinedToPayrollCreditMinutes;
        $bankCreditMinutes = $destinacaoResult->destinedToBankCreditMinutes;
        $bankDebitMinutes = $destinacaoResult->destinedToBankDebitMinutes;
        $destinedToPayrollMinutes = $destinacaoResult->destinedToPayrollCreditMinutes;
        $destinedToCompensationMinutes = $destinacaoResult->destinedToMonthlyCompensationMinutes;
        $isPendingSettlement = $classificacaoResult->isPendingLegalDefinition;

        if ($isPendingSettlement && ! empty($classificacaoResult->notes)) {
            foreach ($classificacaoResult->notes as $cNote) {
                if (! in_array($cNote, $treatmentNotes, true)) {
                    $treatmentNotes[] = $cNote;
                }
            }
        }

        // 13. Apuração de trabalho noturno (CalculateNightWorkAction)
        $nightWorkAction = app(CalculateNightWorkAction::class);
        $nightWorkResult = $nightWorkAction->execute(
            workedIntervals: $workedIntervals,
            timezone: $establishment?->timezone ?? 'America/Sao_Paulo',
            profile: $ruleProfile,
            schedule: $workSchedule,
            breakIntervals: $breakIntervals,
            context: [
                'is_holiday' => $calendarDay->isHoliday,
                'calendar_day' => $calendarDay,
                'shift' => $shift,
                'legal_regime' => $employee->legal_regime,
                'is_12x36' => $is12x36,
            ]
        );

        $physicalNightMinutes = (int) round($nightWorkResult->physicalNightSeconds / 60);
        $legalNightEquivalentMinutes = (int) round($nightWorkResult->legalNightEquivalentSeconds / 60);
        $nightFictionalBonusMinutes = max(0, $legalNightEquivalentMinutes - $physicalNightMinutes);

        if ($physicalNightMinutes > 0 || $nightWorkResult->nightExtensionSeconds > 0) {
            $notesSuffix = $nightWorkResult->nightAdditionalPercentage
                ? sprintf(' com adicional de %s%%', $nightWorkResult->nightAdditionalPercentage)
                : '';
            $treatmentNotes[] = sprintf(
                'Trabalho noturno apurado: %s físicos (%s legal equivalente com hora de 52m30s)%s.',
                $nightWorkResult->formattedPhysicalNight(),
                $nightWorkResult->formattedLegalNightEquivalent(),
                $notesSuffix
            );
        }

        return new CalculatedJourney(
            date: $dateStr,
            scheduledMinutes: $scheduledMinutes,
            workedMinutes: $workedMinutes,
            ordinaryMinutes: $ordinaryMinutes,
            overtimeMinutes: $overtimeMinutes,
            lateMinutes: $lateMinutes,
            earlyLeaveMinutes: $earlyLeaveMinutes,
            breakMinutes: $breakMinutes,
            missingBreakMinutes: $missingBreakMinutes,
            absenceMinutes: $absenceMinutes,
            isIncomplete: $isIncomplete,
            bankCreditMinutes: $bankCreditMinutes,
            bankDebitMinutes: $bankDebitMinutes,
            holidayMinutes: $holidayMinutes,
            requiresCompensation: $calendarDay->requiresCompensation || $requiresCompensation,
            effectivePunches: $effectivePunches,
            treatmentNotes: $treatmentNotes,
            calendarSnapshot: $calendarSnapshot,
            nightWorkResult: $nightWorkResult,
            physicalNightMinutes: $physicalNightMinutes,
            legalNightEquivalentMinutes: $legalNightEquivalentMinutes,
            nightFictionalBonusMinutes: $nightFictionalBonusMinutes,
            compensableOvertimeMinutes: $compensableOvertimeMinutes,
            nonCompensableOvertimeMinutes: $nonCompensableOvertimeMinutes,
            hasSchedule: $hasSchedule,
            isPendingConfiguration: $isPendingConfiguration,
            laborRuleProfileId: $laborRuleProfileId,
            laborRuleProfileCode: $laborRuleProfileCode,
            workScheduleId: $workScheduleId,
            workScheduleCode: $workScheduleCode,
            shiftAssignmentId: $shiftAssignmentId,
            shiftCode: $shiftCode,
            settlementModality: $destinacaoResult->modality,
            settlementPolicyId: $resolvedSettlementPolicy->id,
            settlementPolicyName: $resolvedSettlementPolicy->name,
            destinedToPayrollMinutes: $destinedToPayrollMinutes,
            destinedToCompensationMinutes: $destinedToCompensationMinutes,
            isPendingSettlement: $isPendingSettlement,
            settlementNotes: $destinacaoResult->notes,
            settlementSummary: $settlementSummary,
            toleratedMinutes: $toleratedMinutes ?: ($classificacaoResult->toleratedMinutes ?? 0),
            justifiedMinutes: $classificacaoResult->justifiedMinutes ?? 0,
            isFuture: $isFutureDay,
            isInProgress: $isInProgressDay,
            identifiedShiftTurn: $identifiedShiftTurn,
            identifiedShiftTurnLabel: $identifiedShiftTurnLabel,
        );
    }
}
