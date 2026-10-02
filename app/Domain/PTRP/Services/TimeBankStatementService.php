<?php

namespace App\Domain\PTRP\Services;

use App\Domain\PTRP\DTOs\TimeBankMonthlySummary;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Models\Employee;
use App\Models\TimeBankAccount;
use Carbon\Carbon;

class TimeBankStatementService
{
    public function getMonthlySummary(Employee $employee, int $year, int $month): TimeBankMonthlySummary
    {
        $account = TimeBankAccount::getOrCreateForEmployee($employee);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // 1. Saldo anterior ao início do mês
        $previousBalance = (int) $account->transactions()
            ->where('reference_date', '<', $startDate->toDateString())
            ->sum('minutes');

        // 2. Transações ocorridas no mês de referência
        $monthTransactions = $account->transactions()
            ->whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $monthCredits = (int) $monthTransactions
            ->where('type', TimeBankTransactionType::OvertimeCredit)
            ->sum('minutes');

        $monthDebits = (int) $monthTransactions
            ->where('type', TimeBankTransactionType::CompensationDebit)
            ->sum('minutes');

        $monthAdjustments = (int) $monthTransactions
            ->whereIn('type', [TimeBankTransactionType::ManualCredit, TimeBankTransactionType::ManualDebit])
            ->sum('minutes');

        $closingReset = (int) $monthTransactions
            ->where('type', TimeBankTransactionType::MonthlyReset)
            ->sum('minutes');

        $currentBalance = (int) $account->transactions()
            ->where('reference_date', '<=', $endDate->toDateString())
            ->sum('minutes');

        return new TimeBankMonthlySummary(
            previousBalanceMinutes: $previousBalance,
            monthCreditsMinutes: $monthCredits,
            monthDebitsMinutes: $monthDebits,
            monthAdjustmentsMinutes: $monthAdjustments,
            closingResetMinutes: $closingReset,
            currentBalanceMinutes: $currentBalance,
            transactions: $monthTransactions,
        );
    }
}
