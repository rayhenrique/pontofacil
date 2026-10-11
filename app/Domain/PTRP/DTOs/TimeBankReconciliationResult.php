<?php

namespace App\Domain\PTRP\DTOs;

use App\Models\TimeBankAccount;

class TimeBankReconciliationResult
{
    /**
     * @param  array<string>  $issues
     * @param  array<string>  $divergencesExplanation
     * @param  array<array<string, mixed>>  $duplicateTransactions
     * @param  array<array<string, mixed>>  $postClosingTransactions
     */
    public function __construct(
        public bool $isConsistent,
        public bool $hasDuplicates,
        public bool $hasPostClosingTransactions,
        public bool $hasLegacyReset,
        public bool $hasDiscrepancy,
        public int $expectedFinalBalanceMinutes,
        public int $recordedFinalBalanceMinutes,
        public int $discrepancyMinutes,
        public int $eligibleCreditsMinutes,
        public int $eligibleDebitsMinutes,
        public int $authorizedAdjustmentsMinutes,
        public array $issues = [],
        public array $divergencesExplanation = [],
        public array $duplicateTransactions = [],
        public array $postClosingTransactions = [],
        public string $status = 'conciliated',
        public string $statusLabel = 'Conciliado',
        public string $badgeClass = 'bg-emerald-50 text-emerald-800 border-emerald-200',
    ) {}

    public function formattedExpectedBalance(): string
    {
        return TimeBankAccount::formatMinutes($this->expectedFinalBalanceMinutes);
    }

    public function formattedRecordedBalance(): string
    {
        return TimeBankAccount::formatMinutes($this->recordedFinalBalanceMinutes);
    }

    public function formattedDiscrepancy(): string
    {
        return TimeBankAccount::formatMinutes($this->discrepancyMinutes);
    }

    public function isClean(): bool
    {
        return $this->isConsistent && ! $this->hasDuplicates && ! $this->hasPostClosingTransactions && ! $this->hasLegacyReset;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_consistent' => $this->isConsistent,
            'has_duplicates' => $this->hasDuplicates,
            'has_post_closing_transactions' => $this->hasPostClosingTransactions,
            'has_legacy_reset' => $this->hasLegacyReset,
            'has_discrepancy' => $this->hasDiscrepancy,
            'expected_final_balance_minutes' => $this->expectedFinalBalanceMinutes,
            'expected_final_balance_formatted' => $this->formattedExpectedBalance(),
            'recorded_final_balance_minutes' => $this->recordedFinalBalanceMinutes,
            'recorded_final_balance_formatted' => $this->formattedRecordedBalance(),
            'discrepancy_minutes' => $this->discrepancyMinutes,
            'discrepancy_formatted' => $this->formattedDiscrepancy(),
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'badge_class' => $this->badgeClass,
            'issues' => $this->issues,
            'divergences_explanation' => $this->divergencesExplanation,
            'duplicate_transactions_count' => count($this->duplicateTransactions),
            'post_closing_transactions_count' => count($this->postClosingTransactions),
        ];
    }
}
