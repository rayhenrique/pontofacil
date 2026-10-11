<?php

namespace App\Domain\Settlement\DTOs;

/**
 * Representa estritamente o fato analítico apurado:
 * horas previstas, trabalhadas e diferenças brutas (positivas ou negativas).
 * Sem qualquer juízo sobre pagamento, desconto ou banco de horas.
 */
class WorkTimeApuracaoResult
{
    public function __construct(
        public string $date,
        public int $scheduledMinutes,
        public int $workedMinutes,
        public int $grossDifferenceMinutes, // worked - scheduled
        public int $grossOvertimeMinutes,
        public int $grossLateMinutes,
        public int $grossEarlyLeaveMinutes,
        public int $grossAbsenceMinutes,
        public int $breakMinutes,
        public int $missingBreakMinutes,
        public int $physicalNightMinutes = 0,
        public int $legalNightEquivalentMinutes = 0,
        public bool $hasSchedule = true,
        public bool $isDayOff = false,
        public bool $isHoliday = false,
    ) {}

    public function isPositive(): bool
    {
        return $this->grossDifferenceMinutes > 0;
    }

    public function isNegative(): bool
    {
        return $this->grossDifferenceMinutes < 0;
    }

    public function isZero(): bool
    {
        return $this->grossDifferenceMinutes === 0;
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'scheduled_minutes' => $this->scheduledMinutes,
            'worked_minutes' => $this->workedMinutes,
            'gross_difference_minutes' => $this->grossDifferenceMinutes,
            'gross_overtime_minutes' => $this->grossOvertimeMinutes,
            'gross_late_minutes' => $this->grossLateMinutes,
            'gross_early_leave_minutes' => $this->grossEarlyLeaveMinutes,
            'gross_absence_minutes' => $this->grossAbsenceMinutes,
            'break_minutes' => $this->breakMinutes,
            'missing_break_minutes' => $this->missingBreakMinutes,
            'physical_night_minutes' => $this->physicalNightMinutes,
            'legal_night_equivalent_minutes' => $this->legalNightEquivalentMinutes,
            'has_schedule' => $this->hasSchedule,
            'is_day_off' => $this->is_day_off ?? $this->isDayOff,
            'is_holiday' => $this->is_holiday ?? $this->isHoliday,
        ];
    }
}
