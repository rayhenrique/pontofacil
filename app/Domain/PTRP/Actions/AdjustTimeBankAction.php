<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Models\Employee;
use App\Models\TimeBankTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class AdjustTimeBankAction
{
    /**
     * Executa ajuste manual no banco de horas exigindo justificativa obrigatória
     * e registrador administrativo.
     */
    public function execute(
        Employee $employee,
        TimeBankTransactionType $type,
        int $minutes,
        CarbonInterface $date,
        string $reason,
        User $adminUser,
    ): TimeBankTransaction {
        if (! in_array($type, [TimeBankTransactionType::ManualCredit, TimeBankTransactionType::ManualDebit])) {
            throw new \InvalidArgumentException('O tipo de ajuste manual deve ser manual_credit ou manual_debit.');
        }

        if (empty(trim($reason))) {
            throw new \InvalidArgumentException('A justificativa do ajuste manual é estritamente obrigatória.');
        }

        $signedMinutes = $type === TimeBankTransactionType::ManualDebit ? -abs($minutes) : abs($minutes);

        $transaction = app(RecordTimeBankTransactionAction::class)->execute(
            employee: $employee,
            type: $type,
            minutes: $signedMinutes,
            referenceDate: $date,
            description: sprintf('Ajuste manual administrativo (%s minutos)', $signedMinutes > 0 ? "+{$signedMinutes}" : $signedMinutes),
            reason: trim($reason),
            createdBy: $adminUser,
            sourceType: User::class,
            sourceId: (string) $adminUser->id,
        );

        $event = $type === TimeBankTransactionType::ManualCredit ? 'time_bank.manual_credit' : 'time_bank.manual_debit';
        Log::info($event, [
            'employee_id' => $employee->id,
            'minutes' => $signedMinutes,
            'reason' => $reason,
            'admin_id' => $adminUser->id,
        ]);

        return $transaction;
    }
}
