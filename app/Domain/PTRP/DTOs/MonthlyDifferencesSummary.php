<?php

namespace App\Domain\PTRP\DTOs;

use App\Domain\Settlement\Enums\SettlementModality;

class MonthlyDifferencesSummary
{
    /**
     * @param  array<int, string>  $notes
     */
    public function __construct(
        public int $positiveMinutes,
        public int $negativeMinutes, // negativo assinado (ex: -33)
        public int $netMinutes,      // positiveMinutes + negativeMinutes (soma matemática)
        public string $status,        // 'definitive', 'partial', 'historical'
        public string $statusLabel,
        public int $concludedDaysCount,
        public int $pendingDaysCount,
        public int $uncalculableDaysCount,
        public ?SettlementModality $settlementModality = null,
        public bool $operatesTimeBank = false,
        public int $provisionalPositiveMinutes = 0,
        public int $provisionalNegativeMinutes = 0,
        public int $destinedToPayrollMinutes = 0,
        public int $destinedToCompensationMinutes = 0,
        public int $settledMinutes = 0,
        public int $pendingSettlementMinutes = 0,
        public ?string $destinationDescription = null,
        public array $notes = [],
    ) {}

    public function formattedPositive(bool $withColon = false): string
    {
        $hours = intdiv($this->positiveMinutes, 60);
        $rem = $this->positiveMinutes % 60;

        return $withColon
            ? sprintf('+%02d:%02d', $hours, $rem)
            : sprintf('+%02dh %02dm', $hours, $rem);
    }

    public function formattedNegative(bool $withColon = false): string
    {
        $abs = abs($this->negativeMinutes);
        $hours = intdiv($abs, 60);
        $rem = $abs % 60;

        return $withColon
            ? sprintf('-%02d:%02d', $hours, $rem)
            : sprintf('-%02dh %02dm', $hours, $rem);
    }

    public function formattedNet(bool $withColon = false): string
    {
        if ($this->netMinutes === 0) {
            return $withColon ? '00:00' : '00h 00m';
        }

        $sign = $this->netMinutes < 0 ? '-' : '+';
        $abs = abs($this->netMinutes);
        $hours = intdiv($abs, 60);
        $rem = $abs % 60;

        return $withColon
            ? sprintf('%s%02d:%02d', $sign, $hours, $rem)
            : sprintf('%s%02dh %02dm', $sign, $hours, $rem);
    }

    public function isDefinitive(): bool
    {
        return $this->status === 'definitive';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function isHistorical(): bool
    {
        return $this->status === 'historical';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'positive_minutes' => $this->positiveMinutes,
            'negative_minutes' => $this->negativeMinutes,
            'net_minutes' => $this->netMinutes,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'concluded_days_count' => $this->concludedDaysCount,
            'pending_days_count' => $this->pendingDaysCount,
            'uncalculable_days_count' => $this->uncalculableDaysCount,
            'formatted_positive' => $this->formattedPositive(),
            'formatted_negative' => $this->formattedNegative(),
            'formatted_net' => $this->formattedNet(),
            'formatted_positive_time' => $this->formattedPositive(true),
            'formatted_negative_time' => $this->formattedNegative(true),
            'formatted_net_time' => $this->formattedNet(true),
            'settlement_modality' => $this->settlementModality?->value,
            'operates_time_bank' => $this->operatesTimeBank,
            'provisional_positive_minutes' => $this->provisionalPositiveMinutes,
            'provisional_negative_minutes' => $this->provisionalNegativeMinutes,
            'destined_to_payroll_minutes' => $this->destinedToPayrollMinutes,
            'destined_to_compensation_minutes' => $this->destinedToCompensationMinutes,
            'settled_minutes' => $this->settledMinutes,
            'pending_settlement_minutes' => $this->pendingSettlementMinutes,
            'destination_description' => $this->destinationDescription,
            'notes' => $this->notes,
        ];
    }
}
