<?php

namespace App\Domain\PTRP\DTOs;

use App\Domain\LaborRules\DTOs\NightWorkCalculationResult;

class CalculatedJourney
{
    /**
     * @param  array<int, array{time: string, type: string, source: string, id: ?string}>  $effectivePunches
     * @param  array<int, string>  $treatmentNotes
     * @param  array<int, array{date: string, type: string, name: string, scope: string, work_behavior: string}>  $calendarSnapshot  Snapshot dos eventos de calendário utilizados na apuração
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
        public int $holidayMinutes = 0,
        public bool $requiresCompensation = false,
        public array $effectivePunches = [],
        public array $treatmentNotes = [],
        public array $calendarSnapshot = [],
        public ?NightWorkCalculationResult $nightWorkResult = null,
        public int $physicalNightMinutes = 0,
        public int $legalNightEquivalentMinutes = 0,
        public int $nightFictionalBonusMinutes = 0,
        public int $compensableOvertimeMinutes = 0,
        public int $nonCompensableOvertimeMinutes = 0,
        public bool $hasSchedule = true,
        public bool $isPendingConfiguration = false,
        public ?int $laborRuleProfileId = null,
        public ?string $laborRuleProfileCode = null,
        public ?int $workScheduleId = null,
        public ?string $workScheduleCode = null,
        public ?int $shiftAssignmentId = null,
        public ?string $shiftCode = null,
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
            'holiday_minutes' => $this->holidayMinutes,
            'requires_compensation' => $this->requiresCompensation,
            'effective_punches' => $this->effectivePunches,
            'treatment_notes' => $this->treatmentNotes,
            'calendar_snapshot' => $this->calendarSnapshot,
            'night_work' => $this->nightWorkResult?->toArray(),
            'physical_night_minutes' => $this->physicalNightMinutes,
            'legal_night_equivalent_minutes' => $this->legalNightEquivalentMinutes,
            'night_fictional_bonus_minutes' => $this->nightFictionalBonusMinutes,
            'compensable_overtime_minutes' => $this->compensableOvertimeMinutes,
            'non_compensable_overtime_minutes' => $this->nonCompensableOvertimeMinutes,
            'has_schedule' => $this->hasSchedule,
            'is_pending_configuration' => $this->isPendingConfiguration,
            'labor_rule_profile_id' => $this->laborRuleProfileId,
            'labor_rule_profile_code' => $this->laborRuleProfileCode,
            'work_schedule_id' => $this->workScheduleId,
            'work_schedule_code' => $this->workScheduleCode,
            'shift_assignment_id' => $this->shiftAssignmentId,
            'shift_code' => $this->shiftCode,
        ];
    }

    public function nightWorkedMinutes(): int
    {
        return $this->physicalNightMinutes ?: ($this->nightWorkResult?->physicalNightMinutes() ?? 0);
    }

    public function legalNightEquivalentMinutes(): int
    {
        return $this->legalNightEquivalentMinutes ?: ($this->nightWorkResult?->legalNightEquivalentMinutes() ?? 0);
    }

    public function nightFictionalBonusMinutes(): int
    {
        return $this->nightFictionalBonusMinutes ?: ($this->nightWorkResult?->nightFictionalBonusMinutes() ?? 0);
    }
}
