<?php

namespace App\Domain\Calendar\Services;

use App\Domain\Calendar\DTOs\ResolvedCalendarDay;
use App\Enums\CalendarEventType;
use App\Enums\WorkBehavior;
use App\Models\CalendarEvent;
use App\Models\Establishment;
use Carbon\CarbonInterface;

/**
 * 20.18.8 — Serviço de resolução do calendário laboral.
 *
 * Consulta os CalendarEvents ativos e resolve, para um dia e estabelecimento,
 * qual é o impacto real sobre a jornada esperada do trabalhador.
 *
 * Fluxo: Employee → Establishment → Date → WorkCalendarService → WorkSchedule → CalculatedJourney
 */
class WorkCalendarService
{
    /**
     * Resolve o calendário laboral para um dia específico.
     *
     * @param  int  $originalScheduledMinutes  Minutos da escala normal (WorkSchedule) para o dia
     */
    public function resolveDay(
        CarbonInterface|string $date,
        ?Establishment $establishment,
        int $originalScheduledMinutes = 0,
        array $expectedPeriods = [],
    ): ResolvedCalendarDay {
        $dateStr = $date instanceof CarbonInterface ? $date->format('Y-m-d') : $date;

        // 20.18.7 — Buscar eventos aplicáveis por hierarquia territorial
        $events = CalendarEvent::forDate($dateStr, $establishment)
            ->orderByRaw("CASE scope
                WHEN 'establishment' THEN 4
                WHEN 'municipal' THEN 3
                WHEN 'state' THEN 2
                WHEN 'national' THEN 1
                ELSE 0
            END DESC")
            ->get();

        if ($events->isEmpty()) {
            return new ResolvedCalendarDay(
                date: $dateStr,
                isHoliday: false,
                isOptionalDay: false,
                expectedWork: $originalScheduledMinutes > 0,
                expectedMinutes: $originalScheduledMinutes,
                specialStart: null,
                specialEnd: null,
                requiresCompensation: false,
                appliedEvents: [],
            );
        }

        // Determinar estado global do dia a partir de todos os eventos aplicáveis.
        // O evento mais específico (maior prioridade de escopo) governa o work_behavior.
        $dominantEvent = $events->first();
        $isHoliday = $events->contains(fn (CalendarEvent $e) => $e->type === CalendarEventType::Holiday);
        $isOptionalDay = $events->contains(fn (CalendarEvent $e) => $e->type === CalendarEventType::OptionalDay);
        $requiresCompensation = $events->contains(fn (CalendarEvent $e) => $e->requires_compensation);

        // Resolver minutos esperados e períodos especiais
        $expectedMinutes = $originalScheduledMinutes;
        $specialStart = null;
        $specialEnd = null;
        $expectedWork = true;

        $behavior = $dominantEvent->work_behavior;

        if ($behavior->suspendsWork()) {
            // 20.18.9, 20.18.11, 20.18.12 — Jornada zerada
            if ($dominantEvent->all_day) {
                $expectedMinutes = 0;
                $expectedWork = false;
            } else {
                // 20.18.6 — Evento parcial: suspende apenas o intervalo especificado
                $specialStart = $dominantEvent->starts_at ? substr((string) $dominantEvent->starts_at, 0, 5) : null;
                $specialEnd = $dominantEvent->ends_at ? substr((string) $dominantEvent->ends_at, 0, 5) : null;

                // Calcular minutos do período suspenso e descontar da jornada esperada
                if ($specialStart && $specialEnd) {
                    if (! empty($expectedPeriods)) {
                        $remainingMinutes = 0;
                        foreach ($expectedPeriods as $period) {
                            $pStart = substr((string) ($period['start'] ?? ''), 0, 5);
                            $pEnd = substr((string) ($period['end'] ?? ''), 0, 5);
                            if (! $pStart || ! $pEnd) {
                                continue;
                            }
                            $periodDuration = $this->minutesBetween($pStart, $pEnd);
                            $overlapStart = max($pStart, $specialStart);
                            $overlapEnd = min($pEnd, $specialEnd);
                            $overlapMinutes = ($overlapEnd > $overlapStart)
                                ? $this->minutesBetween($overlapStart, $overlapEnd)
                                : 0;
                            $remainingMinutes += max(0, $periodDuration - $overlapMinutes);
                        }
                        $expectedMinutes = $remainingMinutes;
                    } else {
                        $suspendedMinutes = $this->minutesBetween($specialStart, $specialEnd);
                        $expectedMinutes = max(0, $originalScheduledMinutes - $suspendedMinutes);
                    }
                }
                $expectedWork = $expectedMinutes > 0;
            }
        } elseif ($behavior === WorkBehavior::ReducedWorkday) {
            // 20.18.6 — Expediente reduzido com horários configurados
            if (! $dominantEvent->all_day && $dominantEvent->starts_at && $dominantEvent->ends_at) {
                $specialStart = substr((string) $dominantEvent->starts_at, 0, 5);
                $specialEnd = substr((string) $dominantEvent->ends_at, 0, 5);

                if (! empty($expectedPeriods)) {
                    $activeMinutes = 0;
                    foreach ($expectedPeriods as $period) {
                        $pStart = substr((string) ($period['start'] ?? ''), 0, 5);
                        $pEnd = substr((string) ($period['end'] ?? ''), 0, 5);
                        if (! $pStart || ! $pEnd) {
                            continue;
                        }
                        $overlapStart = max($pStart, $specialStart);
                        $overlapEnd = min($pEnd, $specialEnd);
                        if ($overlapEnd > $overlapStart) {
                            $activeMinutes += $this->minutesBetween($overlapStart, $overlapEnd);
                        }
                    }
                    $expectedMinutes = $activeMinutes;
                } else {
                    $expectedMinutes = $this->minutesBetween($specialStart, $specialEnd);
                }
            }
            $expectedWork = $expectedMinutes > 0;
        }
        // NormalWorkday: mantém $expectedMinutes = $originalScheduledMinutes

        return new ResolvedCalendarDay(
            date: $dateStr,
            isHoliday: $isHoliday,
            isOptionalDay: $isOptionalDay,
            expectedWork: $expectedWork,
            expectedMinutes: $expectedMinutes,
            specialStart: $specialStart,
            specialEnd: $specialEnd,
            requiresCompensation: $requiresCompensation,
            appliedEvents: $events->all(),
        );
    }

    /**
     * Calcula os minutos entre dois horários HH:MM.
     */
    private function minutesBetween(string $start, string $end): int
    {
        $startParts = explode(':', $start);
        $endParts = explode(':', $end);
        $startMinutes = (int) $startParts[0] * 60 + (int) ($startParts[1] ?? 0);
        $endMinutes = (int) $endParts[0] * 60 + (int) ($endParts[1] ?? 0);

        return max(0, $endMinutes - $startMinutes);
    }
}
