<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\Calendar\Services\WorkCalendarService;
use App\Domain\LaborRules\Actions\CalculateNightWorkAction;
use App\Domain\LaborRules\DTOs\WorkInterval;
use App\Domain\PTRP\DTOs\CalculatedJourney;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Policies\LaborPolicy;
use App\Domain\Settlement\DTOs\WorkTimeApuracaoResult;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
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

class CalculateDailyJourneyAction
{
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
    ): CalculatedJourney {
        $dateStr = $date->format('Y-m-d');

        // 1. Resolver Plantão (ShiftAssignment) e Escala de Trabalho (WorkSchedule)
        $shift = ShiftAssignment::where('employee_id', $employee->id)
            ->active()
            ->whereDate('start_at_local', $dateStr)
            ->first();

        $workSchedule = $schedule ?? $shift?->workSchedule ?? $employee->getWorkScheduleForDate($date);
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
        $ruleProfile = $employee->getLaborRuleProfileForDate($date);
        $laborRuleProfileId = $ruleProfile?->id;
        $laborRuleProfileCode = $ruleProfile?->code;

        // 3. Política de tolerância CLT (Art. 58, § 1º)
        $laborPolicy = $policy ?? new LaborPolicy(
            $workSchedule?->tolerance_minutes ?? 5,
            $workSchedule?->daily_tolerance_minutes ?? 10
        );

        // 4. Consultar Calendário Laboral (20.18.8)
        $establishment = $employee->sector?->establishment ?? Establishment::first();
        $calendarService = app(WorkCalendarService::class);
        $calendarDay = $calendarService->resolveDay($date, $establishment, $scheduledMinutes, $expectedPeriods);

        $is12x36 = ($shift?->shift_type === ShiftType::Shift12x36) || ($workSchedule?->modality === WorkScheduleModality::TwelveByThirtySix);

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
        $rawPunches = PunchEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('user_id', $employee->user_id);
        })
            ->whereDate('occurred_at_local', $dateStr)
            ->orderBy('occurred_at_local', 'asc')
            ->get();

        // 6. Obter eventos de tratamento aprovados (PTRP)
        $approvedTreatments = TreatmentEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->where('status', TreatmentEventStatus::Approved)
            ->whereDate('effective_at', $dateStr)
            ->get();

        $disregardedPunchIds = $approvedTreatments
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->pluck('reference_punch_id')
            ->filter()
            ->all();

        $justifiedAbsence = $approvedTreatments
            ->where('type', TreatmentEventType::AbsenceJustified)
            ->first();

        // 7. Construir lista de batidas efetivas (Efetivo = Bruto - Desconsideradas + Inclusões Manuais)
        $effectivePunches = [];

        foreach ($rawPunches as $punch) {
            if (in_array($punch->id, $disregardedPunchIds)) {
                $treatmentNotes[] = sprintf('Marcação #%s (%s) desconsiderada por justificativa aprovada.', $punch->nsr, $punch->occurred_at_local->format('H:i'));

                continue;
            }

            $effectivePunches[] = [
                'id' => $punch->id,
                'nsr' => $punch->nsr,
                'time' => $punch->occurred_at_local->format('H:i'),
                'timestamp' => $punch->occurred_at_local,
                'type' => $punch->direction ?? 'punch',
                'direction' => $punch->direction ?? 'in',
                'source' => 'rep_p',
            ];
        }

        // Fallback para histórico legado em TimeEntry se não houver registros em PunchEvent
        if ($rawPunches->isEmpty() && $employee->user_id) {
            $legacyEntries = TimeEntry::where('user_id', $employee->user_id)
                ->whereDate('timestamp', $dateStr)
                ->orderBy('timestamp', 'asc')
                ->get();

            foreach ($legacyEntries as $entry) {
                $entryTime = Carbon::parse($entry->timestamp);
                $dir = match ($entry->type) {
                    'clock_in', 'in' => 'in',
                    'clock_out', 'out' => 'out',
                    default => 'punch',
                };
                $effectivePunches[] = [
                    'id' => (string) $entry->id,
                    'nsr' => 0,
                    'time' => $entryTime->format('H:i'),
                    'timestamp' => $entryTime,
                    'type' => $dir,
                    'direction' => $dir,
                    'source' => 'time_entry_projection',
                ];
            }
        }

        // Adicionar batidas manuais tratadas
        foreach ($approvedTreatments->where('type', TreatmentEventType::ManualPunchAdded) as $manual) {
            $dir = $manual->new_value_json['direction'] ?? 'manual';
            $effectivePunches[] = [
                'id' => $manual->id,
                'nsr' => 0,
                'time' => $manual->effective_at->format('H:i'),
                'timestamp' => $manual->effective_at,
                'type' => $dir,
                'direction' => $dir,
                'source' => 'ptrp_manual',
                'reason' => $manual->reason_text,
            ];
            $treatmentNotes[] = sprintf('Batida manual inserida às %s (%s).', $manual->effective_at->format('H:i'), $manual->reason_text);
        }

        // Ordenar cronologicamente
        usort($effectivePunches, fn ($a, $b) => strcmp($a['time'], $b['time']));

        // 8. Verificar se a primeira batida do dia é a saída de uma jornada noturna iniciada na véspera
        if (! empty($effectivePunches)) {
            $firstDir = $effectivePunches[0]['direction'] ?? $effectivePunches[0]['type'];
            if ($firstDir === 'out') {
                $prevDate = $date->copy()->subDay();
                $prevDateStr = $prevDate->format('Y-m-d');
                $prevShift = ShiftAssignment::where('employee_id', $employee->id)
                    ->active()
                    ->whereDate('start_at_local', $prevDateStr)
                    ->where('crosses_midnight', true)
                    ->first();

                $prevPunches = PunchEvent::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                        ->orWhere('user_id', $employee->user_id);
                })
                    ->whereDate('occurred_at_local', $prevDateStr)
                    ->orderBy('occurred_at_local', 'asc')
                    ->get();

                if ($prevPunches->isEmpty() && $employee->user_id) {
                    $prevPunches = TimeEntry::where('user_id', $employee->user_id)
                        ->whereDate('timestamp', $prevDateStr)
                        ->orderBy('timestamp', 'asc')
                        ->get();
                }

                if ($prevPunches->isNotEmpty()) {
                    $lastPrev = $prevPunches->last();
                    $lastPrevDir = $lastPrev->direction ?? (match ($lastPrev->type ?? '') {
                        'clock_in', 'in' => 'in',
                        'clock_out', 'out' => 'out',
                        default => 'in',
                    });
                    $lastPrevTime = Carbon::parse($lastPrev->occurred_at_local ?? $lastPrev->timestamp);
                    $currTime = Carbon::parse($effectivePunches[0]['timestamp'] ?? ($dateStr.' '.$effectivePunches[0]['time']));

                    $isNightShiftCross = ($prevShift !== null && $prevShift->crosses_midnight);
                    $isHeuristicCross = ($lastPrevDir === 'in' && ($lastPrevTime->hour >= 18 || $isNightShiftCross) && $currTime->diffInHours($lastPrevTime) <= 24);

                    if ($isNightShiftCross || $isHeuristicCross) {
                        $effectivePunches[0]['is_previous_day_exit'] = true;
                        $treatmentNotes[] = sprintf('Saída das %s vinculada à jornada noturna iniciada no dia anterior (%s).', $effectivePunches[0]['time'], $prevDateStr);
                    }
                }
            }
        }

        // 9. Se o último registro do dia for uma entrada noturna sem saída, buscar saída no dia seguinte
        $unpairedToday = array_values(array_filter($effectivePunches, fn ($p) => empty($p['is_previous_day_exit'])));
        $lastTodayPunch = ! empty($unpairedToday) ? end($unpairedToday) : null;
        $lastTodayDir = $lastTodayPunch ? ($lastTodayPunch['direction'] ?? $lastTodayPunch['type']) : null;

        if ($lastTodayPunch && $lastTodayDir === 'in') {
            $lastEntryTime = Carbon::parse($lastTodayPunch['timestamp'] ?? ($dateStr.' '.$lastTodayPunch['time']));
            $isNightShift = ($shift !== null && $shift->crosses_midnight) || ($lastEntryTime->hour >= 18);

            if ($isNightShift) {
                $nextDate = $date->copy()->addDay();
                $nextDateStr = $nextDate->format('Y-m-d');
                $nextPunches = PunchEvent::where(function ($q) use ($employee) {
                    $q->where('employee_id', $employee->id)
                        ->orWhere('user_id', $employee->user_id);
                })
                    ->whereDate('occurred_at_local', $nextDateStr)
                    ->orderBy('occurred_at_local', 'asc')
                    ->get();

                if ($nextPunches->isEmpty() && $employee->user_id) {
                    $nextPunches = TimeEntry::where('user_id', $employee->user_id)
                        ->whereDate('timestamp', $nextDateStr)
                        ->orderBy('timestamp', 'asc')
                        ->get();
                }

                if ($nextPunches->isNotEmpty()) {
                    $firstNext = $nextPunches->first();
                    $firstNextDir = $firstNext->direction ?? (match ($firstNext->type ?? '') {
                        'clock_out', 'out' => 'out',
                        default => 'out',
                    });
                    $firstNextTime = Carbon::parse($firstNext->occurred_at_local ?? $firstNext->timestamp);

                    if ($firstNextDir === 'out' && $firstNextTime->diffInHours($lastEntryTime) <= 24 && $firstNextTime->greaterThan($lastEntryTime)) {
                        $effectivePunches[] = [
                            'id' => (string) $firstNext->id,
                            'nsr' => $firstNext->nsr ?? 0,
                            'time' => $firstNextTime->format('H:i'),
                            'timestamp' => $firstNextTime,
                            'type' => 'out',
                            'direction' => 'out',
                            'source' => 'rep_p_next_day_nocturnal',
                            'is_next_day_exit' => true,
                        ];
                        $treatmentNotes[] = sprintf('Jornada noturna concluída no dia seguinte às %s.', $firstNextTime->format('H:i'));
                    }
                }
            }
        }

        // 10. Pareamento Direcional e Cálculo de Minutos Trabalhados e Intervalos
        $punchesToCalculate = array_values(array_filter($effectivePunches, fn ($p) => empty($p['is_previous_day_exit'])));
        $workedMinutes = 0;
        $breakMinutes = 0;
        $workedIntervals = [];
        $breakIntervals = [];
        $isIncomplete = false;
        $currentEntry = null;
        $lastExitTime = null;

        foreach ($punchesToCalculate as $p) {
            $dir = $p['direction'] ?? $p['type'] ?? 'punch';
            if ($dir === 'clock_in') {
                $dir = 'in';
            } elseif ($dir === 'clock_out') {
                $dir = 'out';
            }

            $pTime = Carbon::parse($p['timestamp'] ?? ($dateStr.' '.$p['time']));

            if ($dir === 'in') {
                if ($currentEntry !== null) {
                    $isIncomplete = true;
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
            $treatmentNotes[] = sprintf('Marcação de entrada às %s em aberto (sem registro de saída).', $currentEntry['time']);
        }

        if (count($punchesToCalculate) % 2 !== 0) {
            $isIncomplete = true;
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

        $is12x36 = ($shift?->shift_type === ShiftType::Shift12x36) || ($workSchedule?->modality === WorkScheduleModality::TwelveByThirtySix);

        if (! $hasSchedule) {
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
        );
    }
}
