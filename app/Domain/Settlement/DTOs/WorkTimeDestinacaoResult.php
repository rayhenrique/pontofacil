<?php

namespace App\Domain\Settlement\DTOs;

use App\Domain\Settlement\Enums\SettlementModality;

/**
 * Representa a destinação formal e legalmente autorizada das horas apuradas e classificadas:
 * banco de horas, folha de pagamento, compensação no mês, folga compensatória ou pendente.
 */
class WorkTimeDestinacaoResult
{
    /**
     * @param  array<int, string>  $notes
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public SettlementModality $modality,
        public int $destinedToBankCreditMinutes = 0,
        public int $destinedToBankDebitMinutes = 0,
        public int $destinedToMonthlyCompensationMinutes = 0,
        public int $destinedToPayrollCreditMinutes = 0,
        public int $destinedToPayrollDebitMinutes = 0,
        public int $destinedToDayOffMinutes = 0,
        public int $pendingMinutes = 0,
        public bool $isPendingDefinition = false,
        public array $notes = [],
        public array $details = [],
    ) {}

    public function toArray(): array
    {
        return [
            'modality' => $this->modality->value,
            'modality_label' => $this->modality->label(),
            'destined_to_bank_credit_minutes' => $this->destinedToBankCreditMinutes,
            'destined_to_bank_debit_minutes' => $this->destinedToBankDebitMinutes,
            'destined_to_monthly_compensation_minutes' => $this->destinedToMonthlyCompensationMinutes,
            'destined_to_payroll_credit_minutes' => $this->destinedToPayrollCreditMinutes,
            'destined_to_payroll_debit_minutes' => $this->destinedToPayrollDebitMinutes,
            'destined_to_day_off_minutes' => $this->destinedToDayOffMinutes,
            'pending_minutes' => $this->pendingMinutes,
            'is_pending_definition' => $this->isPendingDefinition,
            'notes' => $this->notes,
            'details' => $this->details,
        ];
    }
}
