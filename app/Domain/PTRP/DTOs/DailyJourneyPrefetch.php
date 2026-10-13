<?php

namespace App\Domain\PTRP\DTOs;

use App\Domain\Calendar\DTOs\ResolvedCalendarDay;
use App\Models\Establishment;
use App\Models\LaborRuleProfile;
use App\Models\ShiftAssignment;
use App\Models\WorkSchedule;
use Illuminate\Support\Collection;

/**
 * Dados diários pré-carregados para uma jornada do motor PTRP.
 *
 * Quando fornecido ao CalculateDailyJourneyAction, elimina as consultas
 * individuais por colaborador/dia sem alterar nenhum resultado da apuração.
 * Cada grupo possui uma flag explícita de resolução para distinguir
 * "ausência de dado" (nulo autoritativo) de "dado não fornecido"
 * (nesse caso o motor consulta o banco como antes).
 *
 * As marcações brutas e os tratamentos aprovados do dia continuam
 * trafegando pelos parâmetros próprios do motor
 * ($prefetchedRawPunches / $prefetchedApprovedTreatments).
 */
class DailyJourneyPrefetch
{
    /** @var Collection<int, mixed> Registros legados do dia (TimeEntry, ordem cronológica). */
    public Collection $legacyEntries;

    /** @var Collection<int, mixed> Marcações do dia anterior (com fallback legado já aplicado). */
    public Collection $prevDayPunches;

    /** @var Collection<int, mixed> Marcações do dia seguinte (com fallback legado já aplicado). */
    public Collection $nextDayPunches;

    /** @var Collection<int, mixed>|null Eventos de calendário aplicáveis ao dia (ordenados por prioridade). */
    public ?Collection $calendarEvents;

    public function __construct(
        public bool $shiftResolved = false,
        public ?ShiftAssignment $shift = null,
        public bool $scheduleResolved = false,
        public ?WorkSchedule $resolvedSchedule = null,
        public ?Establishment $establishment = null,
        public bool $ruleProfileResolved = false,
        public ?LaborRuleProfile $ruleProfile = null,
        public ?ResolvedCalendarDay $calendarDay = null,
        public bool $legacyResolved = false,
        public bool $neighborsResolved = false,
        public ?ShiftAssignment $prevShift = null,
        ?Collection $legacyEntries = null,
        ?Collection $prevDayPunches = null,
        ?Collection $nextDayPunches = null,
        ?Collection $calendarEvents = null,
    ) {
        $this->legacyEntries = $legacyEntries ?? collect();
        $this->prevDayPunches = $prevDayPunches ?? collect();
        $this->nextDayPunches = $nextDayPunches ?? collect();
        $this->calendarEvents = $calendarEvents;
    }
}
