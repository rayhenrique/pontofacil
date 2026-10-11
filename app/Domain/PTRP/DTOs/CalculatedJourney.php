<?php

namespace App\Domain\PTRP\DTOs;

use App\Domain\LaborRules\DTOs\NightWorkCalculationResult;
use App\Domain\Settlement\DTOs\WorkTimeSettlementSummary;
use App\Domain\Settlement\Enums\SettlementModality;

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
        public ?SettlementModality $settlementModality = null,
        public ?int $settlementPolicyId = null,
        public ?string $settlementPolicyName = null,
        public int $destinedToPayrollMinutes = 0,
        public int $destinedToCompensationMinutes = 0,
        public bool $isPendingSettlement = false,
        public array $settlementNotes = [],
        public ?WorkTimeSettlementSummary $settlementSummary = null,
        public int $toleratedMinutes = 0,
        public int $justifiedMinutes = 0,
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

    public function dailyDifferenceMinutes(): ?int
    {
        if (! $this->hasSchedule || $this->isPendingConfiguration || $this->isIncomplete) {
            return null;
        }

        // Se o colaborador não trabalhou mas a ausência foi integralmente abonada/justificada
        if ($this->workedMinutes === 0 && $this->scheduledMinutes > 0 && $this->absenceMinutes === 0) {
            return 0;
        }

        return $this->workedMinutes - $this->scheduledMinutes;
    }

    public function differenceState(): string
    {
        if (! $this->hasSchedule || $this->isPendingConfiguration) {
            return 'uncalculable';
        }

        if ($this->isIncomplete) {
            return 'pending';
        }

        return 'calculated';
    }

    public function formattedDifference(): string
    {
        return match ($this->differenceState()) {
            'uncalculable' => 'Saldo indisponível — escala não configurada',
            'pending' => 'Apuração pendente',
            'calculated' => self::formatDifferenceMinutes($this->dailyDifferenceMinutes() ?? 0),
        };
    }

    public static function formatDifferenceMinutes(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }

        $sign = $minutes < 0 ? '-' : '+';
        $abs = abs($minutes);
        $hours = intdiv($abs, 60);
        $rem = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $hours, $rem);
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
            'daily_difference_minutes' => $this->dailyDifferenceMinutes(),
            'difference_state' => $this->differenceState(),
            'formatted_difference' => $this->formattedDifference(),
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
            'settlement_modality' => $this->settlementModality?->value,
            'settlement_policy_id' => $this->settlementPolicyId,
            'settlement_policy_name' => $this->settlementPolicyName,
            'destined_to_payroll_minutes' => $this->destinedToPayrollMinutes,
            'destined_to_compensation_minutes' => $this->destinedToCompensationMinutes,
            'is_pending_settlement' => $this->isPendingSettlement,
            'settlement_notes' => $this->settlementNotes,
            'settlement_summary' => $this->settlementSummary?->toArray(),
            'tolerated_minutes' => $this->toleratedMinutes,
            'justified_minutes' => $this->justifiedMinutes,
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
