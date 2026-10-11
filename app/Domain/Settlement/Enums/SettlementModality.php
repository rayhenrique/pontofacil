<?php

namespace App\Domain\Settlement\Enums;

enum SettlementModality: string
{
    case NoBank = 'no_bank';
    case MonthlyCompensation = 'monthly_compensation';
    case CumulativeBank = 'cumulative_bank';
    case DirectPayroll = 'direct_payroll';
    case TimeOff = 'time_off';
    case Hybrid = 'hybrid';
    case LegacyMonthlyReset = 'legacy_monthly_reset';

    public function label(): string
    {
        return match ($this) {
            self::NoBank => 'Sem Banco de Horas (Apenas Apuração Analítica)',
            self::MonthlyCompensation => 'Compensação dentro da Competência Mensal',
            self::CumulativeBank => 'Banco de Horas Acumulativo (Transporte de Saldo)',
            self::DirectPayroll => 'Horas Extras Destinadas Diretamente à Folha',
            self::TimeOff => 'Compensação Exclusiva por Folgas Programadas',
            self::Hybrid => 'Modelo Misto (Banco de Horas + Folha Excedente)',
            self::LegacyMonthlyReset => 'Zeramento Mensal de Fechamento (Legado)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NoBank => 'Diferenças de jornada são apuradas e classificadas, mas nenhum saldo é creditado ou debitado em ledger de banco de horas.',
            self::MonthlyCompensation => 'Positivos e negativos compensam entre si exclusivamente no mês apurado. Não há transporte de saldo para o mês seguinte.',
            self::CumulativeBank => 'Saldos compensáveis são creditados ou debitados no ledger de banco de horas com transporte autorizado pelo instrumento normativo.',
            self::DirectPayroll => 'Horas extras autorizadas são direcionadas para liquidação em folha de pagamento com adicionais legais.',
            self::TimeOff => 'Saldos positivos destinam-se exclusivamente à concessão de descansos compensatórios.',
            self::Hybrid => 'Parte do saldo é acumulada em banco de horas até um teto mensal/diário; o excedente é destinado para pagamento em folha.',
            self::LegacyMonthlyReset => 'Modo legado onde créditos/débitos ocorrem durante o mês e o saldo remanescente é zerado no fechamento mensal formal.',
        };
    }

    public function operatesTimeBankLedger(): bool
    {
        return match ($this) {
            self::CumulativeBank, self::Hybrid, self::LegacyMonthlyReset => true,
            self::NoBank, self::MonthlyCompensation, self::DirectPayroll, self::TimeOff => false,
        };
    }
}
