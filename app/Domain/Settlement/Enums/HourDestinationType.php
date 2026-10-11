<?php

namespace App\Domain\Settlement\Enums;

enum HourDestinationType: string
{
    case BankCredit = 'bank_credit';
    case BankDebit = 'bank_debit';
    case MonthlyOffset = 'monthly_offset';
    case PayrollPayment = 'payroll_payment';
    case PayrollDeduction = 'payroll_deduction';
    case DayOffGranted = 'day_off_granted';
    case PendingDefinition = 'pending_definition';

    public function label(): string
    {
        return match ($this) {
            self::BankCredit => 'Crédito no Banco de Horas',
            self::BankDebit => 'Débito no Banco de Horas',
            self::MonthlyOffset => 'Compensação na Competência',
            self::PayrollPayment => 'Pagamento em Folha de Pagamento',
            self::PayrollDeduction => 'Desconto em Folha de Pagamento',
            self::DayOffGranted => 'Concessão de Folga Compensatória',
            self::PendingDefinition => 'Pendente de Definição / Sem Destinação Automática',
        };
    }
}
