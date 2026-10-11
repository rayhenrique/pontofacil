<?php

namespace App\Domain\Settlement\Enums;

enum SettlementDischargeType: string
{
    case PayrollPaid = 'payroll_paid';
    case PayrollDeducted = 'payroll_deducted';
    case TimeOffEnjoyed = 'time_off_enjoyed';
    case CompensatedInPeriod = 'compensated_in_period';
    case CarriedOver = 'carried_over';
    case MonthlyResetLegacy = 'monthly_reset_legacy';
    case TermOfSettlement = 'term_of_settlement';

    public function label(): string
    {
        return match ($this) {
            self::PayrollPaid => 'Hora Extra Paga em Folha',
            self::PayrollDeducted => 'Déficit Descontado em Folha',
            self::TimeOffEnjoyed => 'Folga Compensatória Usufruída',
            self::CompensatedInPeriod => 'Compensado dentro da Competência',
            self::CarriedOver => 'Transportado no Banco de Horas',
            self::MonthlyResetLegacy => 'Zeramento Mensal de Fechamento (Legado)',
            self::TermOfSettlement => 'Termo de Quitação Formal',
        };
    }
}
