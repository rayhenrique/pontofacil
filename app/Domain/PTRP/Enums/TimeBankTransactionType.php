<?php

namespace App\Domain\PTRP\Enums;

enum TimeBankTransactionType: string
{
    case OvertimeCredit = 'overtime_credit';
    case CompensationDebit = 'compensation_debit';
    case ManualCredit = 'manual_credit';
    case ManualDebit = 'manual_debit';
    case MonthlyReset = 'monthly_reset';
    case OpeningBalance = 'opening_balance';
    case Expiration = 'expiration';

    public function label(): string
    {
        return match ($this) {
            self::OvertimeCredit => 'Crédito de Horas Extras',
            self::CompensationDebit => 'Débito por Compensação / Atraso',
            self::ManualCredit => 'Crédito Manual Administrativo',
            self::ManualDebit => 'Débito Manual Administrativo',
            self::MonthlyReset => 'Zeramento Mensal de Fechamento',
            self::OpeningBalance => 'Saldo Inicial Transportado',
            self::Expiration => 'Prescrição / Expiração de Saldo',
        };
    }
}
