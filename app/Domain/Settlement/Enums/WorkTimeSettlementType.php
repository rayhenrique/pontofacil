<?php

namespace App\Domain\Settlement\Enums;

enum WorkTimeSettlementType: string
{
    case TimeOffCompensation = 'time_off_compensation';
    case DeficitCompensation = 'deficit_compensation';
    case PayrollPayment = 'payroll_payment';
    case PayrollDeduction = 'payroll_deduction';
    case CarryOver = 'carry_over';
    case AdministrativeAdjustment = 'administrative_adjustment';
    case LegacyMonthlyReset = 'legacy_monthly_reset';
    case OtherPolicyDestination = 'other_policy_destination';

    public function label(): string
    {
        return match ($this) {
            self::TimeOffCompensation => 'Compensação por Descanso (Folga)',
            self::DeficitCompensation => 'Compensação de Déficit Autorizada',
            self::PayrollPayment => 'Pagamento em Folha (Horas Extras)',
            self::PayrollDeduction => 'Desconto em Folha (Déficit/Falta)',
            self::CarryOver => 'Transporte para Banco de Horas',
            self::AdministrativeAdjustment => 'Ajuste Administrativo Fundamentado',
            self::LegacyMonthlyReset => 'Zeramento Mensal Legado',
            self::OtherPolicyDestination => 'Outra Destinação Prevista na Política',
        };
    }

    public function isCredit(): bool
    {
        return match ($this) {
            self::TimeOffCompensation, self::PayrollPayment => true,
            self::DeficitCompensation, self::PayrollDeduction => false,
            default => true,
        };
    }

    public function requiresExecutionProof(): bool
    {
        return match ($this) {
            self::TimeOffCompensation,
            self::PayrollPayment,
            self::PayrollDeduction,
            self::DeficitCompensation,
            self::AdministrativeAdjustment => true,
            default => false,
        };
    }
}
