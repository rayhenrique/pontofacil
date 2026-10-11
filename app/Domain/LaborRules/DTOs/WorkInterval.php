<?php

namespace App\Domain\LaborRules\DTOs;

use Carbon\CarbonInterface;

class WorkInterval
{
    public function __construct(
        public CarbonInterface $start,
        public CarbonInterface $end,
        public string $type = 'worked',
        public bool $isBreak = false,
        public ?string $description = null,
    ) {}

    public function durationInSeconds(): int
    {
        return max(0, $this->end->getTimestamp() - $this->start->getTimestamp());
    }

    public function durationInMinutes(): int
    {
        return (int) round($this->durationInSeconds() / 60);
    }

    public function overlaps(CarbonInterface $from, CarbonInterface $to): bool
    {
        return $this->start->lt($to) && $this->end->gt($from);
    }

    /**
     * Calcula a interseção entre este intervalo e outro período de tempo.
     */
    public function intersection(CarbonInterface $from, CarbonInterface $to): ?self
    {
        if (! $this->overlaps($from, $to)) {
            return null;
        }

        $interStart = $this->start->gt($from) ? $this->start->copy() : $from->copy();
        $interEnd = $this->end->lt($to) ? $this->end->copy() : $to->copy();

        if ($interEnd->lte($interStart)) {
            return null;
        }

        return new self(
            start: $interStart,
            end: $interEnd,
            type: $this->type,
            isBreak: $this->isBreak,
            description: $this->description
        );
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->toIso8601String(),
            'end' => $this->end->toIso8601String(),
            'type' => $this->type,
            'is_break' => $this->isBreak,
            'duration_seconds' => $this->durationInSeconds(),
            'duration_minutes' => $this->durationInMinutes(),
        ];
    }
}
