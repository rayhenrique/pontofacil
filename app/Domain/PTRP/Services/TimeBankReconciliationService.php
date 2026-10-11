<?php

namespace App\Domain\PTRP\Services;

use App\Domain\PTRP\DTOs\TimeBankMonthlySummary;
use App\Domain\PTRP\DTOs\TimeBankReconciliationResult;
use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Domain\Settlement\Enums\WorkTimeSettlementStatus;
use App\Models\ClosedPeriod;
use App\Models\Employee;
use App\Models\TimeBankAccount;
use App\Models\TimeBankTransaction;
use App\Models\WorkTimeSettlement;
use Carbon\Carbon;

class TimeBankReconciliationService
{
    /**
     * Executa conciliação contábil entre o saldo inicial, movimentações do ledger,
     * destinações auditáveis e apuração da jornada PTRP.
     *
     * IMPORTANTE: Esta operação é estritamente de leitura (somente auditoria).
     * Não insere, não altera e não remove lançamentos no banco de dados.
     *
     * @param  array<string, mixed>|null  $daysCalculated
     */
    public function reconcile(
        Employee $employee,
        int $year,
        int $month,
        TimeBankMonthlySummary $summary,
        ?array $daysCalculated = null,
        ?ClosedPeriod $closedPeriod = null
    ): TimeBankReconciliationResult {
        $issues = [];
        $divergencesExplanation = [];
        $duplicateTransactions = [];
        $postClosingTransactions = [];

        // 1. Verificação da Equação Contábil Fundamental do Ledger
        // Saldo Inicial + Entradas Elegíveis - Saídas Elegíveis + Ajustes Autorizados + Zeramentos/Quitações = Saldo Final
        $prev = $summary->previousBalanceMinutes;
        $credits = $summary->monthCreditsMinutes;
        $debits = $summary->monthDebitsMinutes; // preserva sinal negativo
        $adjustments = $summary->monthAdjustmentsMinutes;
        $reset = $summary->closingResetMinutes;

        $expectedFinal = $prev + $credits + $debits + $adjustments + $reset;
        $recordedFinal = $summary->closingBalanceMinutes;
        $discrepancy = $recordedFinal - $expectedFinal;
        $hasDiscrepancy = ($discrepancy !== 0);

        if ($hasDiscrepancy) {
            $issues[] = sprintf(
                'Divergência matemática no ledger: o saldo esperado pela soma das movimentações (%s) difere do saldo final apurado (%s). Diferença: %s.',
                TimeBankAccount::formatMinutes($expectedFinal),
                TimeBankAccount::formatMinutes($recordedFinal),
                TimeBankAccount::formatMinutes($discrepancy)
            );
        }

        // 2. Detecção de Operações Duplicadas
        $transactions = $summary->transactions;
        $seen = [];

        foreach ($transactions as $tx) {
            $date = is_array($tx) ? ($tx['reference_date'] ?? '') : ($tx->reference_date ? $tx->reference_date->format('Y-m-d') : '');
            $mins = (int) (is_array($tx) ? ($tx['minutes'] ?? 0) : $tx->minutes);
            $type = is_array($tx) ? ($tx['type'] ?? '') : ($tx->type instanceof TimeBankTransactionType ? $tx->type->value : (string) $tx->type);
            $id = is_array($tx) ? ($tx['id'] ?? '') : (string) $tx->id;

            $fingerprint = sprintf('%s|%d|%s', $date, $mins, $type);

            if (isset($seen[$fingerprint])) {
                $duplicateTransactions[] = [
                    'original_id' => $seen[$fingerprint]['id'],
                    'duplicate_id' => $id,
                    'date' => $date,
                    'minutes' => $mins,
                    'type' => $type,
                ];
                $issues[] = sprintf(
                    'Operação potencialmente duplicada detectada no dia %s: lançamentos %s e %s com valor idêntico de %s.',
                    Carbon::parse($date)->format('d/m/Y'),
                    $seen[$fingerprint]['id'],
                    $id,
                    TimeBankAccount::formatMinutes($mins)
                );
            } else {
                $seen[$fingerprint] = ['id' => $id, 'date' => $date, 'minutes' => $mins];
            }
        }

        // 3. Verificação de Lançamentos Registrados Após o Fechamento da Competência
        $closedPeriod ??= ClosedPeriod::findForPeriod($year, $month);
        if ($closedPeriod && $closedPeriod->closed_at) {
            $closedAt = $closedPeriod->closed_at;
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfDay();
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

            $account = TimeBankAccount::where('employee_id', $employee->id)->first();
            if ($account) {
                $postTx = TimeBankTransaction::where('time_bank_account_id', $account->id)
                    ->whereBetween('reference_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->where('created_at', '>', $closedAt)
                    ->get();

                foreach ($postTx as $ptx) {
                    $postClosingTransactions[] = [
                        'id' => $ptx->id,
                        'reference_date' => $ptx->reference_date->toDateString(),
                        'minutes' => $ptx->minutes,
                        'type' => $ptx->type->value,
                        'created_at' => $ptx->created_at->toDateTimeString(),
                    ];
                    $issues[] = sprintf(
                        'Lançamento posterior ao fechamento: transação %s (%s) foi gravada no ledger em %s, após o fechamento formal ocorrido em %s.',
                        $ptx->id,
                        TimeBankAccount::formatMinutes($ptx->minutes),
                        $ptx->created_at->format('d/m/Y H:i'),
                        $closedAt->format('d/m/Y H:i')
                    );
                }
            }
        }

        // 4. Verificação de Estornos e Quitações Parciais Registradas
        $refPeriod = sprintf('%04d-%02d', $year, $month);
        $settlements = WorkTimeSettlement::where('employee_id', $employee->id)
            ->where('reference_period', $refPeriod)
            ->get();

        $reversedSettlements = $settlements->where('status', WorkTimeSettlementStatus::Reversed);
        foreach ($reversedSettlements as $rev) {
            $issues[] = sprintf(
                'Estorno registrado: destinação %s no valor de %s foi formalmente estornada. Justificativa: %s.',
                $rev->id,
                TimeBankAccount::formatMinutes($rev->minutes),
                $rev->justification ?? 'Sem justificativa'
            );
        }

        // 5. Comparativo entre Apuração PTRP e Lançamentos Efetivos no Banco de Horas
        if ($daysCalculated && ! empty($daysCalculated)) {
            $ptrpOvertimeMinutes = 0;
            $ptrpDeficitMinutes = 0;
            $toleratedMinutes = 0;
            $payrollDestinedMinutes = 0;
            $unconcludedDaysCount = 0;

            foreach ($daysCalculated as $day) {
                $overtime = (int) ($day['overtime_minutes'] ?? 0);
                $diff = $day['difference_minutes'] ?? null;
                $tolerated = (int) ($day['tolerated_minutes'] ?? 0);
                $destinedPayroll = (int) ($day['destined_to_payroll_minutes'] ?? 0);

                if ($day['is_incomplete'] ?? false) {
                    $unconcludedDaysCount++;
                }

                $ptrpOvertimeMinutes += $overtime;
                $toleratedMinutes += $tolerated;
                $payrollDestinedMinutes += $destinedPayroll;

                if ($diff !== null && $diff < 0) {
                    $ptrpDeficitMinutes += abs($diff);
                }
            }

            // Explicar divergência entre Horas Extras Apuradas no PTRP e Créditos no Banco
            if ($ptrpOvertimeMinutes !== $credits) {
                if ($payrollDestinedMinutes > 0) {
                    $divergencesExplanation[] = sprintf(
                        'Foram apuradas %s de horas extras no PTRP, mas %s foram direcionadas para quitação em folha de pagamento, restando %s registradas no banco de horas.',
                        TimeBankAccount::formatMinutes($ptrpOvertimeMinutes),
                        TimeBankAccount::formatMinutes($payrollDestinedMinutes),
                        TimeBankAccount::formatMinutes($credits)
                    );
                } elseif ($summary->policyMode === 'no_bank' || ! $summary->operatesTimeBank) {
                    $divergencesExplanation[] = sprintf(
                        'As %s apuradas no PTRP não geraram créditos em banco de horas porque o colaborador está sob política sem banco (destinação integral à folha de pagamento).',
                        TimeBankAccount::formatMinutes($ptrpOvertimeMinutes)
                    );
                } elseif ($ptrpOvertimeMinutes > $credits) {
                    $divergencesExplanation[] = sprintf(
                        'Apuração PTRP identificou %s de saldo positivo bruto, enquanto o banco contabilizou %s. A diferença reflete regras de tolerância normativa, quitações ou compensações do período.',
                        TimeBankAccount::formatMinutes($ptrpOvertimeMinutes),
                        TimeBankAccount::formatMinutes($credits)
                    );
                }
            }

            if ($unconcludedDaysCount > 0) {
                $divergencesExplanation[] = sprintf(
                    '%d dia(s) com jornada incompleta ou em aberto não foram integrados ao banco de horas até a finalização da apuração.',
                    $unconcludedDaysCount
                );
            }
        }

        // 6. Zeramento Histórico Legado
        $hasLegacyReset = ($summary->closingResetMinutes !== 0);
        if ($hasLegacyReset) {
            $issues[] = sprintf(
                'Zeramento histórico registrado: valor de %s aplicado via regra legada de zeramento automático. Não equivale a comprovação de pagamento ou quitação fática.',
                TimeBankAccount::formatMinutes($summary->closingResetMinutes)
            );
        }

        // 7. Status Consolidado da Conciliação
        $hasDuplicates = ! empty($duplicateTransactions);
        $hasPostClosing = ! empty($postClosingTransactions);
        $isConsistent = ! $hasDiscrepancy;

        $status = match (true) {
            $hasDiscrepancy => 'divergent',
            $hasDuplicates || $hasPostClosing => 'warning',
            $hasLegacyReset => 'legacy_reset',
            default => 'conciliated',
        };

        $statusLabel = match ($status) {
            'divergent' => 'Divergência Contábil',
            'warning' => 'Ressalvas / Alertas',
            'legacy_reset' => 'Zeramento Legado',
            default => 'Conciliado e Auditado',
        };

        $badgeClass = match ($status) {
            'divergent' => 'bg-rose-50 text-rose-800 border-rose-200',
            'warning' => 'bg-amber-50 text-amber-800 border-amber-200',
            'legacy_reset' => 'bg-slate-100 text-slate-800 border-slate-300',
            default => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        };

        return new TimeBankReconciliationResult(
            isConsistent: $isConsistent,
            hasDuplicates: $hasDuplicates,
            hasPostClosingTransactions: $hasPostClosing,
            hasLegacyReset: $hasLegacyReset,
            hasDiscrepancy: $hasDiscrepancy,
            expectedFinalBalanceMinutes: $expectedFinal,
            recordedFinalBalanceMinutes: $recordedFinal,
            discrepancyMinutes: $discrepancy,
            eligibleCreditsMinutes: $credits,
            eligibleDebitsMinutes: $debits,
            authorizedAdjustmentsMinutes: $adjustments,
            issues: $issues,
            divergencesExplanation: $divergencesExplanation,
            duplicateTransactions: $duplicateTransactions,
            postClosingTransactions: $postClosingTransactions,
            status: $status,
            statusLabel: $statusLabel,
            badgeClass: $badgeClass,
        );
    }
}
