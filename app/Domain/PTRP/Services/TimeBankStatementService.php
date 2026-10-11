<?php

namespace App\Domain\PTRP\Services;

use App\Domain\PTRP\DTOs\TimeBankMonthlySummary;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\Settlement\Services\WorkTimeSettlementService;
use App\Models\ClosedPeriod;
use App\Models\ClosedPeriodEmployeeSnapshot;
use App\Models\Employee;
use App\Models\TimeBankAccount;
use Carbon\Carbon;

class TimeBankStatementService
{
    public function __construct(
        protected ?WorkTimeSettlementService $settlementService = null
    ) {
        $this->settlementService ??= app(WorkTimeSettlementService::class);
    }

    /**
     * Obtém o resumo analítico e contábil do banco de horas para a competência.
     * Garante que competências antigas não incorporem transações de períodos posteriores.
     */
    public function getMonthlySummary(Employee $employee, int $year, int $month): TimeBankMonthlySummary
    {
        $account = TimeBankAccount::getOrCreateForEmployee($employee);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // 1. Saldo estritamente anterior ao início do mês
        $previousBalance = (int) $account->transactions()
            ->whereDate('reference_date', '<', $startDate->toDateString())
            ->sum('minutes');

        // 2. Transações ocorridas rigorosamente dentro do mês pesquisado
        $monthTransactions = $account->transactions()
            ->whereDate('reference_date', '>=', $startDate->toDateString())
            ->whereDate('reference_date', '<=', $endDate->toDateString())
            ->get();

        $monthCredits = (int) $monthTransactions
            ->filter(fn ($t) => $t->type === TimeBankTransactionType::OvertimeCredit || ($t->minutes > 0 && ! in_array($t->type, [
                TimeBankTransactionType::ManualCredit,
                TimeBankTransactionType::OpeningBalance,
            ])))
            ->sum('minutes');

        $monthDebits = (int) $monthTransactions
            ->filter(fn ($t) => $t->type === TimeBankTransactionType::CompensationDebit || ($t->minutes < 0 && ! in_array($t->type, [
                TimeBankTransactionType::ManualDebit,
                TimeBankTransactionType::MonthlyReset,
                TimeBankTransactionType::Expiration,
            ])))
            ->sum('minutes');

        $manualCredits = (int) $monthTransactions
            ->filter(fn ($t) => $t->type === TimeBankTransactionType::ManualCredit || ($t->type === TimeBankTransactionType::ManualDebit && $t->minutes > 0))
            ->sum('minutes');

        $manualDebits = (int) $monthTransactions
            ->filter(fn ($t) => $t->type === TimeBankTransactionType::ManualDebit || ($t->type === TimeBankTransactionType::ManualCredit && $t->minutes < 0))
            ->sum('minutes');

        $monthAdjustments = $manualCredits + $manualDebits;

        $closingReset = (int) $monthTransactions
            ->filter(fn ($t) => $t->type === TimeBankTransactionType::MonthlyReset || ($t->type instanceof TimeBankTransactionType && $t->type->value === 'monthly_reset') || $t->type === 'monthly_reset')
            ->sum('minutes');

        // 3. Saldo ao final da competência pesquisada (não incorpora movimentações posteriores)
        $closingBalance = (int) $account->transactions()
            ->whereDate('reference_date', '<=', $endDate->toDateString())
            ->sum('minutes');

        // 4. Saldo total da conta na data corrente (para referência analítica quando pesquisado mês passado)
        $todayBalance = (int) $account->currentBalance();

        // 5. Política aplicável
        $settlementPolicy = $this->settlementService->resolvePolicy($employee, $endDate);
        $operatesTimeBank = $settlementPolicy->operatesTimeBank();

        return new TimeBankMonthlySummary(
            previousBalanceMinutes: $previousBalance,
            monthCreditsMinutes: $monthCredits,
            monthDebitsMinutes: $monthDebits,
            monthAdjustmentsMinutes: $monthAdjustments,
            closingResetMinutes: $closingReset,
            currentBalanceMinutes: $closingBalance,
            transactions: $monthTransactions,
            closingBalanceMinutes: $closingBalance,
            todayBalanceMinutes: $todayBalance,
            manualCreditsMinutes: $manualCredits,
            manualDebitsMinutes: $manualDebits,
            compensationsMinutes: 0,
            isClosedPeriod: false,
            isHistoricalLimitation: false,
            historicalLimitationMessage: null,
            policyMode: $settlementPolicy->modality->value,
            policyName: $settlementPolicy->name,
            operatesTimeBank: $operatesTimeBank,
            destinationDescription: $operatesTimeBank
                ? 'Banco de horas ativo com controle contábil de entradas e saídas.'
                : 'Vínculo sem banco de horas ativo no período (destinação para folha de pagamento).',
        );
    }

    /**
     * Resolve o resumo do banco de horas de acordo com o estado da competência:
     * - Se fechada com snapshot auditado: lê EXCLUSIVAMENTE do snapshot imutável.
     * - Se aberta: calcula em tempo real pelo ledger até a data final do mês.
     */
    public function getSummaryForPeriod(
        Employee $employee,
        int $year,
        int $month,
        ?ClosedPeriod $closedPeriod = null,
        ?ClosedPeriodEmployeeSnapshot $snapshot = null
    ): TimeBankMonthlySummary {
        if ($closedPeriod && $closedPeriod->status === 'closed' && $snapshot && ! empty($snapshot->time_bank_snapshot)) {
            return TimeBankMonthlySummary::fromSnapshot(
                snapshotData: $snapshot->time_bank_snapshot,
                year: $year,
                month: $month,
                employee: $employee,
                closedPeriod: $closedPeriod
            );
        }

        return $this->getMonthlySummary($employee, $year, $month);
    }
}
