<?php

namespace App\Domain\Settlement\Enums;

enum SettlementOriginType: string
{
    case DailyJourney = 'daily_journey';
    case MonthlyClosingBalance = 'monthly_closing_balance';
    case TimeBankBalance = 'time_bank_balance';
    case AdministrativeDiscrepancy = 'administrative_discrepancy';

    public function label(): string
    {
        return match ($this) {
            self::DailyJourney => 'Apuração Diária',
            self::MonthlyClosingBalance => 'Saldo de Fechamento da Competência',
            self::TimeBankBalance => 'Saldo do Banco de Horas',
            self::AdministrativeDiscrepancy => 'Ajuste / Discrepância Administrativa',
        };
    }
}
