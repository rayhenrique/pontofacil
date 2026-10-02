<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TimeBankClosingMode;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Models\ClosedPeriod;
use App\Models\TimeBankAccount;
use App\Models\TimeBankPolicy;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloseMonthlyPeriodAction
{
    /**
     * Fecha formalmente uma competência mensal.
     *
     * Regra Crucial: O sistema NÃO zera banco de horas automaticamente por mudança de calendário.
     * O zeramento (quando configurado como MONTHLY_RESET) ocorre exclusivamente
     * durante este ato formal de fechamento pelo Admin/RH.
     */
    public function execute(
        int $year,
        int $month,
        User $closedBy,
        ?string $notes = null,
    ): ClosedPeriod {
        if (ClosedPeriod::isClosed($year, $month)) {
            throw new \DomainException(sprintf('A competência %02d/%04d já se encontra fechada e congelada.', $month, $year));
        }

        $periodDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        // 1. Localizar política de banco de horas vigente na competência
        $policy = TimeBankPolicy::forDate($periodDate);

        return DB::transaction(function () use ($year, $month, $closedBy, $notes, $periodDate, $policy) {
            // 2. Aplicar regra da política (se ativada)
            if ($policy && $policy->enabled) {
                if ($policy->closing_mode === TimeBankClosingMode::MonthlyReset) {
                    // Zerar saldo criando transação compensatória sem apagar o histórico
                    $accounts = TimeBankAccount::where('active', true)->get();

                    foreach ($accounts as $account) {
                        $currentBalance = $account->currentBalance();

                        if ($currentBalance !== 0) {
                            $resetMinutes = -$currentBalance; // Inverte o sinal para zerar o saldo contábil

                            app(RecordTimeBankTransactionAction::class)->execute(
                                employee: $account->employee_id,
                                type: TimeBankTransactionType::MonthlyReset,
                                minutes: $resetMinutes,
                                referenceDate: $periodDate,
                                description: sprintf('Zeramento de fechamento da competência %02d/%04d', $month, $year),
                                reason: 'Aplicação da política MONTHLY_RESET no fechamento formal do período',
                                createdBy: $closedBy,
                                sourceType: ClosedPeriod::class,
                                sourceId: null,
                            );

                            Log::info('time_bank.monthly_reset', [
                                'account_id' => $account->id,
                                'employee_id' => $account->employee_id,
                                'period' => sprintf('%02d/%04d', $month, $year),
                                'previous_balance' => $currentBalance,
                                'reset_minutes' => $resetMinutes,
                                'closed_by' => $closedBy->id,
                            ]);
                        }
                    }
                }
                // Se for CARRY_OVER: nenhuma transação artificial é criada, o saldo simplesmente continua
            }

            // 3. Congelar a competência
            $closedPeriod = ClosedPeriod::create([
                'year' => $year,
                'month' => $month,
                'policy_id' => $policy?->id,
                'policy_snapshot' => $policy ? [
                    'id' => $policy->id,
                    'name' => $policy->name,
                    'enabled' => $policy->enabled,
                    'closing_mode' => $policy->closing_mode->value,
                    'valid_from' => $policy->valid_from?->toDateString(),
                ] : null,
                'status' => 'closed',
                'closed_by' => $closedBy->id,
                'closed_at' => now(),
                'notes' => $notes,
            ]);

            Log::info('period.closed', [
                'year' => $year,
                'month' => $month,
                'closed_by' => $closedBy->id,
                'policy_mode' => $policy?->closing_mode?->value ?? 'DISABLED',
            ]);

            return $closedPeriod;
        });
    }
}
