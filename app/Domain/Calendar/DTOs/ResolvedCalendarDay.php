<?php

namespace App\Domain\Calendar\DTOs;

use App\Models\CalendarEvent;

/**
 * 20.18.8 — Retorno do WorkCalendarService.
 *
 * Representa a resolução do calendário laboral para um dia + estabelecimento específico.
 *
 * @property array<int, CalendarEvent> $appliedEvents Eventos aplicáveis resolvidos (ordenados por prioridade de escopo)
 */
class ResolvedCalendarDay
{
    /**
     * @param  array<int, CalendarEvent>  $appliedEvents
     */
    public function __construct(
        public string $date,
        public bool $isHoliday,
        public bool $isOptionalDay,
        public bool $expectedWork,
        public int $expectedMinutes,
        public ?string $specialStart,
        public ?string $specialEnd,
        public bool $requiresCompensation,
        public array $appliedEvents = [],
    ) {}

    /**
     * Serializa para inclusão no snapshot de fechamento (20.18.18).
     *
     * @return array<int, array{date: string, type: string, name: string, scope: string, work_behavior: string, all_day: bool, starts_at: ?string, ends_at: ?string, requires_compensation: bool, legal_reference: ?string}>
     */
    public function toSnapshotArray(): array
    {
        return array_map(
            fn (CalendarEvent $event) => $event->toSnapshotArray(),
            $this->appliedEvents
        );
    }
}
