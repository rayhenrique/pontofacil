<?php

namespace App\Domain\PTRP\DTOs;

class CalculatedJourney
{
    /**
     * @param  array<int, array{time: string, type: string, source: string, id: ?string}>  $effectivePunches
     * @param  array<int, string>  $treatmentNotes
     */
    public function __construct(
        public string $date,
        public int $scheduledMinutes,
        public int $workedMinutes,
        public int $ordinaryMinutes,
        public int $overtimeMinutes,
        public int $lateMinutes,
        public int $earlyLeaveMinutes,
        public int $breakMinutes,
        public int $missingBreakMinutes,
        public int $absenceMinutes,
        public bool $isIncomplete,
        public int $bankCreditMinutes,
        public int $bankDebitMinutes,
        public array $effectivePunches = [],
        public array $treatmentNotes = [],
    ) {}

    public static function formatMinutes(int $minutes, bool $withSign = false): string
    {
        $sign = $minutes < 0 ? '-' : ($withSign ? '+' : '');
        $abs = abs($minutes);
        $hours = intdiv($abs, 60);
        $rem = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $hours, $rem);
    }

    public function formattedWorked(): string
    {
        return self::formatMinutes($this->workedMinutes);
    }

    public function formattedScheduled(): string
    {
        return self::formatMinutes($this->scheduledMinutes);
    }

    public function formattedOvertime(): string
    {
        return self::formatMinutes($this->overtimeMinutes, true);
    }

    public function formattedLate(): string
    {
        return self::formatMinutes($this->lateMinutes);
    }

    public function formattedNetBank(): string
    {
        $net = $this->bankCreditMinutes - $this->bankDebitMinutes;

        return self::formatMinutes($net, true);
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'scheduled_minutes' => $this->scheduledMinutes,
            'worked_minutes' => $this->workedMinutes,
            'ordinary_minutes' => $this->ordinaryMinutes,
            'overtime_minutes' => $this->overtimeMinutes,
            'late_minutes' => $this->lateMinutes,
            'early_leave_minutes' => $this->earlyLeaveMinutes,
            'break_minutes' => $this->breakMinutes,
            'missing_break_minutes' => $this->missingBreakMinutes,
            'absence_minutes' => $this->absenceMinutes,
            'is_incomplete' => $this->isIncomplete,
            'bank_credit_minutes' => $this->bankCreditMinutes,
            'bank_debit_minutes' => $this->bankDebitMinutes,
            'effective_punches' => $this->effectivePunches,
            'treatment_notes' => $this->treatmentNotes,
        ];
    }
}
