<?php

namespace App\Domain\LaborRules\DTOs;

use App\Domain\LaborRules\Enums\NightCalculationStatus;

class NightWorkCalculationResult
{
    /**
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>  $appliedParameters
     * @param  array<int, array<string, mixed>>  $breakdown
     */
    public function __construct(
        public int $physicalWorkedSeconds,
        public int $physicalNightSeconds,
        public int $legalNightEquivalentSeconds,
        public int $nightExtensionSeconds,
        public ?float $nightAdditionalPercentage,
        public ?int $nightRuleProfileId,
        public ?int $ruleVersion,
        public NightCalculationStatus $calculationStatus,
        public array $warnings = [],
        public ?string $ruleName = null,
        public ?string $legalReference = null,
        public array $appliedParameters = [],
        public array $breakdown = [],
    ) {}

    public function physicalWorkedMinutes(): int
    {
        return (int) round($this->physicalWorkedSeconds / 60);
    }

    public function physicalNightMinutes(): int
    {
        return (int) round($this->physicalNightSeconds / 60);
    }

    public function legalNightEquivalentMinutes(): int
    {
        return (int) round($this->legalNightEquivalentSeconds / 60);
    }

    public function nightExtensionMinutes(): int
    {
        return (int) round($this->nightExtensionSeconds / 60);
    }

    /**
     * Acréscimo legal fictício decorrente da hora reduzida (CLT art. 73 § 1º / Lei 8.112 art. 75).
     * NUNCA representa tempo físico de permanência.
     */
    public function nightFictionalBonusSeconds(): int
    {
        $basePhysical = $this->physicalNightSeconds + $this->nightExtensionSeconds;

        return max(0, $this->legalNightEquivalentSeconds - $basePhysical);
    }

    public function nightFictionalBonusMinutes(): int
    {
        return (int) round($this->nightFictionalBonusSeconds() / 60);
    }

    public static function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remSeconds = $seconds % 60;

        if ($remSeconds > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $remSeconds);
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function formattedPhysicalWorked(): string
    {
        return self::formatSeconds($this->physicalWorkedSeconds);
    }

    public function formattedPhysicalNight(): string
    {
        return self::formatSeconds($this->physicalNightSeconds);
    }

    public function formattedLegalNightEquivalent(): string
    {
        return self::formatSeconds($this->legalNightEquivalentSeconds);
    }

    public function formattedNightExtension(): string
    {
        return self::formatSeconds($this->nightExtensionSeconds);
    }

    public function toArray(): array
    {
        return [
            'physical_worked_seconds' => $this->physicalWorkedSeconds,
            'physical_worked_minutes' => $this->physicalWorkedMinutes(),
            'physical_night_seconds' => $this->physicalNightSeconds,
            'physical_night_minutes' => $this->physicalNightMinutes(),
            'legal_night_equivalent_seconds' => $this->legalNightEquivalentSeconds,
            'legal_night_equivalent_minutes' => $this->legalNightEquivalentMinutes(),
            'night_extension_seconds' => $this->nightExtensionSeconds,
            'night_extension_minutes' => $this->nightExtensionMinutes(),
            'night_fictional_bonus_seconds' => $this->nightFictionalBonusSeconds(),
            'night_fictional_bonus_minutes' => $this->nightFictionalBonusMinutes(),
            'night_additional_percentage' => $this->nightAdditionalPercentage,
            'night_rule_profile_id' => $this->nightRuleProfileId,
            'rule_name' => $this->ruleName,
            'rule_version' => $this->ruleVersion,
            'legal_reference' => $this->legalReference,
            'calculation_status' => $this->calculationStatus->value,
            'calculation_status_label' => $this->calculationStatus->label(),
            'warnings' => $this->warnings,
            'applied_parameters' => $this->appliedParameters,
            'breakdown' => $this->breakdown,
        ];
    }
}
