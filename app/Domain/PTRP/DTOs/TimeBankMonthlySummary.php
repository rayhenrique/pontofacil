<?php

namespace App\Domain\PTRP\DTOs;

use App\Models\TimeBankAccount;
use Illuminate\Support\Collection;

class TimeBankMonthlySummary
{
    public function __construct(
        public int $previousBalanceMinutes,
        public int $monthCreditsMinutes,
        public int $monthDebitsMinutes,
        public int $monthAdjustmentsMinutes,
        public int $closingResetMinutes,
        public int $currentBalanceMinutes,
        public Collection $transactions,
    ) {}

    public function formattedPreviousBalance(): string
    {
        return TimeBankAccount::formatMinutes($this->previousBalanceMinutes);
    }

    public function formattedMonthCredits(): string
    {
        return TimeBankAccount::formatMinutes($this->monthCreditsMinutes);
    }

    public function formattedMonthDebits(): string
    {
        return TimeBankAccount::formatMinutes($this->monthDebitsMinutes);
    }

    public function formattedMonthAdjustments(): string
    {
        return TimeBankAccount::formatMinutes($this->monthAdjustmentsMinutes);
    }

    public function formattedClosingReset(): string
    {
        return TimeBankAccount::formatMinutes($this->closingResetMinutes);
    }

    public function formattedCurrentBalance(): string
    {
        return TimeBankAccount::formatMinutes($this->currentBalanceMinutes);
    }
}
