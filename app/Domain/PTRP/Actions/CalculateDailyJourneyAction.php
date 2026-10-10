<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\Calendar\Services\WorkCalendarService;
use App\Domain\PTRP\DTOs\CalculatedJourney;
use App\Domain\PTRP\Enums\TreatmentEventStatus;
use App\Domain\PTRP\Enums\TreatmentEventType;
use App\Domain\PTRP\Policies\LaborPolicy;
use App\Models\Employee;
use App\Models\Establishment;
use App\Models\PunchEvent;
use App\Models\TimeEntry;
use App\Models\TreatmentEvent;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CalculateDailyJourneyAction
{
    /**
     * Combina fatos brutos do REP (PunchEvent) + Tratamentos Aprovados (TreatmentEvent)
     * + Escala de Trabalho (WorkSchedule) + Calendário Laboral (CalendarEvent)
     * + Diretrizes Legais (LaborPolicy) para apurar a jornada analítica do trabalhador.
     *
     * A MARCAÇÃO ORIGINAL EM PUNCH_EVENTS NUNCA É ALTERADA.
     */
    public function execute(
        Employee $employee,
        CarbonInterface $date,
        ?WorkSchedule $schedule = null,
        ?LaborPolicy $policy = null,
    ): CalculatedJourney {
        $dateStr = $date->format('Y-m-d');

        // 1. Escala de trabalho esperada
        $workSchedule = $schedule ?? $employee->workSchedule ?? WorkSchedule::first();
        if (! $workSchedule) {
            $workSchedule = WorkSchedule::createDefault40h();
        }

        $daySchedule = $workSchedule->getScheduleForDay($date->dayOfWeek);
        $originalScheduledMinutes = (int) ($daySchedule['expected_minutes'] ?? 0);
        $expectedPeriods = $daySchedule['periods'] ?? [];

        // 2. Política de tolerância CLT (Art. 58, § 1º)
        $laborPolicy = $policy ?? new LaborPolicy(
            $workSchedule->tolerance_minutes ?? 5,
            $workSchedule->daily_tolerance_minutes ?? 10
        );

        // 3. Consultar Calendário Laboral (20.18.8)
        $establishment = $employee->sector?->establishment ?? Establishment::first();
        $calendarService = app(WorkCalendarService::class);
        $calendarDay = $calendarService->resolveDay($date, $establishment, $originalScheduledMinutes, $expectedPeriods);

        // Os minutos esperados podem ser alterados pelo calendário
        $scheduledMinutes = $calendarDay->expectedMinutes;
        $calendarSnapshot = $calendarDay->toSnapshotArray();

        $treatmentNotes = [];

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

        // 4. Obter marcações brutas do REP (somente leitura)
        $rawPunches = PunchEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('user_id', $employee->user_id);
        })
            ->whereDate('occurred_at_local', $dateStr)
            ->orderBy('occurred_at_local', 'asc')
            ->get();

        // 5. Obter eventos de tratamento aprovados (PTRP)
        $approvedTreatments = TreatmentEvent::where(function ($q) use ($employee) {
            $q->where('employee_id', $employee->id)
                ->orWhere('employment_id', $employee->id);
        })
            ->where('status', TreatmentEventStatus::Approved)
            ->whereDate('effective_at', $dateStr)
            ->get();

        // Identificar desconsiderações e justificativas de ausência
        $disregardedPunchIds = $approvedTreatments
            ->where('type', TreatmentEventType::PunchDisregarded)
            ->pluck('reference_punch_id')
            ->filter()
            ->all();

        $justifiedAbsence = $approvedTreatments
            ->where('type', TreatmentEventType::AbsenceJustified)
            ->first();

        // 6. Construir lista de batidas efetivas (Efetivo = Bruto - Desconsideradas + Inclusões Manuais)
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
                $effectivePunches[] = [
                    'id' => (string) $entry->id,
                    'nsr' => 0,
                    'time' => $entryTime->format('H:i'),
                    'timestamp' => $entryTime,
                    'type' => $entry->type ?? 'punch',
                    'source' => 'time_entry_projection',
                ];
            }
        }

        // Adicionar batidas manuais tratadas
        foreach ($approvedTreatments->where('type', TreatmentEventType::ManualPunchAdded) as $manual) {
            $effectivePunches[] = [
                'id' => $manual->id,
                'nsr' => 0,
                'time' => $manual->effective_at->format('H:i'),
                'timestamp' => $manual->effective_at,
                'type' => 'manual',
                'source' => 'ptrp_manual',
                'reason' => $manual->reason_text,
            ];
            $treatmentNotes[] = sprintf('Batida manual inserida às %s (%s).', $manual->effective_at->format('H:i'), $manual->reason_text);
        }

        // Ordenar cronologicamente
        usort($effectivePunches, fn ($a, $b) => strcmp($a['time'], $b['time']));

        // Verificar se a primeira batida do dia é a saída de uma jornada noturna iniciada na véspera
        if (! empty($effectivePunches) && $effectivePunches[0]['type'] === 'out') {
            $prevDateStr = $date->copy()->subDay()->format('Y-m-d');
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
                $lastPrevType = $lastPrev->direction ?? $lastPrev->type;
                $lastPrevTime = Carbon::parse($lastPrev->occurred_at_local ?? $lastPrev->timestamp);
                $currTime = Carbon::parse($effectivePunches[0]['timestamp'] ?? ($dateStr.' '.$effectivePunches[0]['time']));

                if ($lastPrevType === 'in' && $lastPrevTime->hour >= 18 && $currTime->diffInHours($lastPrevTime) <= 16) {
                    $effectivePunches[0]['is_previous_day_exit'] = true;
                    $treatmentNotes[] = sprintf('Saída das %s vinculada à jornada noturna iniciada no dia anterior.', $effectivePunches[0]['time']);
                }
            }
        }

        // Se o último registro do dia for uma entrada noturna (>=18h), verificar se há saída no dia seguinte
        $unpairedCount = count(array_filter($effectivePunches, fn ($p) => empty($p['is_previous_day_exit'])));
        if ($unpairedCount % 2 !== 0 && end($effectivePunches)['type'] === 'in') {
            $lastEntry = end($effectivePunches);
            $lastEntryTime = Carbon::parse($lastEntry['timestamp'] ?? ($dateStr.' '.$lastEntry['time']));
            if ($lastEntryTime->hour >= 18) {
                $nextDateStr = $date->copy()->addDay()->format('Y-m-d');
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
                    $firstNextType = $firstNext->direction ?? $firstNext->type;
                    $firstNextTime = Carbon::parse($firstNext->occurred_at_local ?? $firstNext->timestamp);

                    if ($firstNextType === 'out' && $firstNextTime->diffInHours($lastEntryTime) <= 16) {
                        $effectivePunches[] = [
                            'id' => (string) $firstNext->id,
                            'nsr' => $firstNext->nsr ?? 0,
                            'time' => $firstNextTime->format('H:i'),
                            'timestamp' => $firstNextTime,
                            'type' => 'out',
                            'source' => 'rep_p_next_day_nocturnal',
                            'is_next_day_exit' => true,
                        ];
                        $treatmentNotes[] = sprintf('Jornada noturna concluída no dia seguinte às %s.', $firstNextTime->format('H:i'));
                    }
                }
            }
        }

        $punchesToCalculate = array_values(array_filter($effectivePunches, fn ($p) => empty($p['is_previous_day_exit'])));
        $punchCount = count($punchesToCalculate);
        $isIncomplete = ($punchCount % 2 !== 0);

        // 7. Calcular minutos trabalhados e intervalos intrajornada
        $workedMinutes = 0;
        $breakMinutes = 0;

        for ($i = 0; $i + 1 < $punchCount; $i += 2) {
            $entry = Carbon::parse($punchesToCalculate[$i]['timestamp'] ?? ($dateStr.' '.$punchesToCalculate[$i]['time']));
            $exit = Carbon::parse($punchesToCalculate[$i + 1]['timestamp'] ?? ($dateStr.' '.$punchesToCalculate[$i + 1]['time']));
            if ($exit->greaterThanOrEqualTo($entry)) {
                $workedMinutes += $entry->diffInMinutes($exit);
            }

            // Intervalo entre a saída do período anterior e a entrada do próximo
            if ($i + 2 < $punchCount) {
                $nextEntry = Carbon::parse($punchesToCalculate[$i + 2]['timestamp'] ?? ($dateStr.' '.$punchesToCalculate[$i + 2]['time']));
                if ($nextEntry->greaterThanOrEqualTo($exit)) {
                    $breakMinutes += $exit->diffInMinutes($nextEntry);
                }
            }
        }

        // 8. Apuração comparativa com a escala e aplicação da tolerância legal (Art. 58 CLT)
        $ordinaryMinutes = 0;
        $overtimeMinutes = 0;
        $lateMinutes = 0;
        $earlyLeaveMinutes = 0;
        $absenceMinutes = 0;
        $missingBreakMinutes = 0;
        $holidayMinutes = 0;

        $expectedBreakMinutes = (int) ($daySchedule['break_minutes'] ?? 0);
        if ($expectedBreakMinutes > 0 && $breakMinutes > 0 && $breakMinutes < $expectedBreakMinutes) {
            $missingBreakMinutes = $expectedBreakMinutes - $breakMinutes;
        }

        // 8.1 — Integração com Calendário Laboral
        if ($calendarDay->isHoliday && ! $calendarDay->expectedWork) {
            // 20.18.9 — Feriado com jornada suspensa
            if ($workedMinutes === 0) {
                $absenceMinutes = 0;
                $ordinaryMinutes = 0;
            } else {
                // 20.18.10 — Trabalho em feriado: classificar separadamente como holiday_minutes
                $holidayMinutes = $workedMinutes;
                $ordinaryMinutes = 0;
                $overtimeMinutes = 0; // NÃO assumir hora extra automaticamente
            }
        } elseif (! $calendarDay->expectedWork && ($calendarDay->isOptionalDay || ! empty($calendarDay->appliedEvents))) {
            // 20.18.11, 20.18.12 — Ponto facultativo ou recesso sem expediente
            if ($workedMinutes === 0) {
                $absenceMinutes = 0;
                $ordinaryMinutes = 0;
            } else {
                // Trabalho em dia de ponto facultativo/recesso sem expediente
                $holidayMinutes = $workedMinutes;
                $ordinaryMinutes = 0;
                $overtimeMinutes = 0;
            }
        } elseif ($scheduledMinutes > 0) {
            // Dia com expediente previsto (normal, reduzido, ou ponto fac. com expediente normal)
            if ($workedMinutes === 0) {
                if ($justifiedAbsence) {
                    $absenceMinutes = 0;
                    $treatmentNotes[] = 'Ausência integral abonada/justificada: '.$justifiedAbsence->reason_text;
                } else {
                    $absenceMinutes = $scheduledMinutes;
                }
            } else {
                $netDifference = $workedMinutes - $scheduledMinutes;

                if ($netDifference > 0) {
                    // Sobrejornada: verificar tolerância
                    $toleratedOvertime = $laborPolicy->applyPunchTolerance($netDifference);
                    if ($toleratedOvertime === 0) {
                        $ordinaryMinutes = $workedMinutes;
                        $overtimeMinutes = 0;
                    } else {
                        $ordinaryMinutes = $scheduledMinutes;
                        $overtimeMinutes = $netDifference;
                    }
                } elseif ($netDifference < 0) {
                    // Déficit: verificar tolerância
                    $deficit = abs($netDifference);
                    $toleratedDeficit = $laborPolicy->applyPunchTolerance($deficit);
                    if ($toleratedDeficit === 0) {
                        $ordinaryMinutes = $scheduledMinutes;
                        $lateMinutes = 0;
                    } else {
                        $ordinaryMinutes = $workedMinutes;
                        $lateMinutes = $deficit;
                    }
                } else {
                    $ordinaryMinutes = $scheduledMinutes;
                }
            }
        } else {
            // Dia sem expediente previsto (DSR/Folga): todo o tempo trabalhado é hora extra
            $overtimeMinutes = $workedMinutes;
            $ordinaryMinutes = 0;
        }

        // 9. Crédito e Débito de Banco de Horas
        // holiday_minutes NÃO entram automaticamente no banco — será definido pela política trabalhista
        $bankCreditMinutes = $overtimeMinutes;
        $bankDebitMinutes = $lateMinutes + $earlyLeaveMinutes + $absenceMinutes;

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
            requiresCompensation: $calendarDay->requiresCompensation,
            effectivePunches: $effectivePunches,
            treatmentNotes: $treatmentNotes,
            calendarSnapshot: $calendarSnapshot,
        );
    }
}
