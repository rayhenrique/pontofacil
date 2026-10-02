<?php

namespace App\Domain\PTRP\Actions;

use App\Domain\PTRP\Enums\TimeBankTransactionType;
use App\Models\Employee;
use App\Models\TimeBankAccount;
use App\Models\TimeBankTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class RecordTimeBankTransactionAction
{
    public function execute(
        Employee|int $employee,
        TimeBankTransactionType $type,
        int $minutes,
        CarbonInterface $referenceDate,
        ?string $description = null,
        ?string $reason = null,
        ?User $createdBy = null,
        ?string $sourceType = null,
        ?string $sourceId = null,
    ): TimeBankTransaction {
        $account = TimeBankAccount::getOrCreateForEmployee($employee);

        $transaction = TimeBankTransaction::create([
            'time_bank_account_id' => $account->id,
            'type' => $type,
            'minutes' => $minutes,
            'reference_date' => $referenceDate->format('Y-m-d'),
            'description' => $description ?? $type->label(),
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => $createdBy?->id,
        ]);

        Log::info('time_bank.transaction_recorded', [
            'account_id' => $account->id,
            'employee_id' => $account->employee_id,
            'type' => $type->value,
            'minutes' => $minutes,
            'reference_date' => $referenceDate->format('Y-m-d'),
            'created_by' => $createdBy?->id,
        ]);

        return $transaction;
    }
}
