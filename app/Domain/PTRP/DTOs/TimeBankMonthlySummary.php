<?php

namespace App\Domain\PTRP\DTOs;

use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\TimeBankAccount;
use App\Models\WorkTimeSettlement;
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
        public int $closingBalanceMinutes = 0,
        public int $todayBalanceMinutes = 0,
        public int $manualCreditsMinutes = 0,
        public int $manualDebitsMinutes = 0,
        public int $compensationsMinutes = 0,
        public bool $isClosedPeriod = false,
        public bool $isHistoricalLimitation = false,
        public ?string $historicalLimitationMessage = null,
        public ?Collection $postClosingSettlements = null,
        public ?string $policyMode = null,
        public ?string $policyName = null,
        public bool $operatesTimeBank = true,
        public ?string $destinationDescription = null,
    ) {
        $this->postClosingSettlements ??= collect();

        // Se closingBalanceMinutes não foi explicitamente passado, assume currentBalanceMinutes
        if ($this->closingBalanceMinutes === 0 && $this->currentBalanceMinutes !== 0) {
            $this->closingBalanceMinutes = $this->currentBalanceMinutes;
        }

        // Se currentBalanceMinutes for 0 e closingBalanceMinutes tiver valor, sincroniza
        if ($this->currentBalanceMinutes === 0 && $this->closingBalanceMinutes !== 0) {
            $this->currentBalanceMinutes = $this->closingBalanceMinutes;
        }

        if ($this->todayBalanceMinutes === 0) {
            $this->todayBalanceMinutes = $this->closingBalanceMinutes;
        }
    }

    public function formattedPreviousBalance(): string
    {
        return TimeBankAccount::formatMinutes($this->previousBalanceMinutes);
    }

    public function formattedMonthCredits(): string
    {
        $abs = abs($this->monthCreditsMinutes);
        $hours = intdiv($abs, 60);
        $rem = $abs % 60;

        return sprintf('%02d:%02d', $hours, $rem);
    }

    public function formattedMonthDebits(): string
    {
        $abs = abs($this->monthDebitsMinutes);
        $hours = intdiv($abs, 60);
        $rem = $abs % 60;

        return sprintf('-%02d:%02d', $hours, $rem);
    }

    public function formattedManualCredits(): string
    {
        return TimeBankAccount::formatMinutes($this->manualCreditsMinutes);
    }

    public function formattedManualDebits(): string
    {
        return TimeBankAccount::formatMinutes($this->manualDebitsMinutes);
    }

    public function formattedMonthAdjustments(): string
    {
        return TimeBankAccount::formatMinutes($this->monthAdjustmentsMinutes);
    }

    public function formattedCompensations(): string
    {
        return TimeBankAccount::formatMinutes($this->compensationsMinutes);
    }

    public function formattedClosingReset(): string
    {
        return TimeBankAccount::formatMinutes($this->closingResetMinutes);
    }

    public function formattedClosingBalance(): string
    {
        return TimeBankAccount::formatMinutes($this->closingBalanceMinutes);
    }

    public function formattedCurrentBalance(): string
    {
        return TimeBankAccount::formatMinutes($this->currentBalanceMinutes);
    }

    public function formattedTodayBalance(): string
    {
        return TimeBankAccount::formatMinutes($this->todayBalanceMinutes);
    }

    /**
     * Constrói o resumo do banco de horas exclusivamente a partir do snapshot congelado de competência fechada.
     * Não altera dados do snapshot e sinaliza limitação histórica caso o snapshot legado seja incompleto.
     *
     * @param  array<string, mixed>  $snapshotData
     */
    public static function fromSnapshot(
        array $snapshotData,
        int $year,
        int $month,
        Employee $employee,
        ?ClosedPeriod $closedPeriod = null
    ): self {
        // Se o snapshot for vazio ou não contiver saldos auditáveis
        $hasBalanceBefore = array_key_exists('balance_before', $snapshotData);
        $hasBalanceClosing = array_key_exists('balance_at_closing', $snapshotData);

        if (! $hasBalanceBefore && ! $hasBalanceClosing) {
            return new self(
                previousBalanceMinutes: 0,
                monthCreditsMinutes: 0,
                monthDebitsMinutes: 0,
                monthAdjustmentsMinutes: 0,
                closingResetMinutes: 0,
                currentBalanceMinutes: 0,
                transactions: collect(),
                closingBalanceMinutes: 0,
                todayBalanceMinutes: 0,
                isClosedPeriod: true,
                isHistoricalLimitation: true,
                historicalLimitationMessage: 'Snapshot histórico legado não contém detalhamento congelado do banco de horas para esta competência.',
                policyMode: $snapshotData['policy_mode'] ?? 'LEGACY',
                policyName: 'Snapshot Histórico Legado',
                operatesTimeBank: false,
                destinationDescription: 'Limitação histórica de dados arquivados.',
            );
        }

        $balanceBefore = (int) ($snapshotData['balance_before'] ?? 0);
        $balanceClosing = (int) ($snapshotData['balance_at_closing'] ?? 0);
        $resetApplied = (int) ($snapshotData['reset_applied'] ?? 0);
        $finalBalance = (int) ($snapshotData['final_balance'] ?? ($resetApplied !== 0 ? 0 : $balanceClosing));

        $rawTx = $snapshotData['transactions'] ?? [];
        $txCollection = collect($rawTx);

        // Agregação dos movimentos congelados
        $monthCredits = 0;
        $monthDebits = 0;
        $manualCredits = 0;
        $manualDebits = 0;

        foreach ($txCollection as $tx) {
            $mins = (int) ($tx['minutes'] ?? 0);
            $type = $tx['type'] ?? '';

            if ($type === TimeBankTransactionType::OvertimeCredit->value || ($mins > 0 && ! in_array($type, [
                TimeBankTransactionType::ManualCredit->value,
                TimeBankTransactionType::OpeningBalance->value,
            ]))) {
                $monthCredits += $mins;
            } elseif ($type === TimeBankTransactionType::CompensationDebit->value || ($mins < 0 && ! in_array($type, [
                TimeBankTransactionType::ManualDebit->value,
                TimeBankTransactionType::MonthlyReset->value,
                TimeBankTransactionType::Expiration->value,
            ]))) {
                $monthDebits += $mins;
            } elseif ($type === TimeBankTransactionType::ManualCredit->value) {
                $manualCredits += $mins;
            } elseif ($type === TimeBankTransactionType::ManualDebit->value) {
                $manualDebits += $mins;
            }
        }

        $monthAdjustments = $manualCredits + $manualDebits;

        // Localizar destinações realizadas APÓS o fechamento da competência (sem alterar o saldo histórico congelado)
        $postClosingSettlements = collect();
        if ($closedPeriod && $closedPeriod->closed_at) {
            $refPeriod = sprintf('%04d-%02d', $year, $month);
            $postClosingSettlements = WorkTimeSettlement::where('employee_id', $employee->id)
                ->where('reference_period', $refPeriod)
                ->where('created_at', '>', $closedPeriod->closed_at)
                ->get();
        }

        $policyMode = $snapshotData['policy_mode'] ?? 'CARRY_OVER';
        $policyName = $snapshotData['settlement_policy']['name'] ?? 'Política Congelada no Snapshot';

        return new self(
            previousBalanceMinutes: $balanceBefore,
            monthCreditsMinutes: $monthCredits,
            monthDebitsMinutes: $monthDebits,
            monthAdjustmentsMinutes: $monthAdjustments,
            closingResetMinutes: $resetApplied,
            currentBalanceMinutes: $finalBalance,
            transactions: $txCollection,
            closingBalanceMinutes: $finalBalance,
            todayBalanceMinutes: $finalBalance,
            manualCreditsMinutes: $manualCredits,
            manualDebitsMinutes: $manualDebits,
            compensationsMinutes: 0,
            isClosedPeriod: true,
            isHistoricalLimitation: false,
            historicalLimitationMessage: null,
            postClosingSettlements: $postClosingSettlements,
            policyMode: $policyMode,
            policyName: $policyName,
            operatesTimeBank: $policyMode !== 'DISABLED',
            destinationDescription: 'Saldo histórico auditado do snapshot imutável.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'previous_balance_minutes' => $this->previousBalanceMinutes,
            'previous_balance_formatted' => $this->formattedPreviousBalance(),
            'month_credits_minutes' => $this->monthCreditsMinutes,
            'month_credits_formatted' => $this->formattedMonthCredits(),
            'month_debits_minutes' => $this->monthDebitsMinutes,
            'month_debits_formatted' => $this->formattedMonthDebits(),
            'month_adjustments_minutes' => $this->monthAdjustmentsMinutes,
            'month_adjustments_formatted' => $this->formattedMonthAdjustments(),
            'closing_reset_minutes' => $this->closingResetMinutes,
            'closing_reset_formatted' => $this->formattedClosingReset(),
            'closing_balance_minutes' => $this->closingBalanceMinutes,
            'closing_balance_formatted' => $this->formattedClosingBalance(),
            'current_balance_minutes' => $this->currentBalanceMinutes,
            'current_balance_formatted' => $this->formattedCurrentBalance(),
            'is_closed_period' => $this->isClosedPeriod,
            'is_historical_limitation' => $this->isHistoricalLimitation,
            'historical_limitation_message' => $this->historicalLimitationMessage,
            'policy_mode' => $this->policyMode,
            'policy_name' => $this->policyName,
            'operates_time_bank' => $this->operatesTimeBank,
        ];
    }
}
