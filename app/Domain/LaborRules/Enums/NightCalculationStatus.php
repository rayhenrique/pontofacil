<?php

namespace App\Domain\LaborRules\Enums;

enum NightCalculationStatus: string
{
    case Calculated = 'calculated';
    case PendingConfiguration = 'pending_configuration';
    case Exempt = 'exempt';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Calculated => 'Apurado com Base Legal',
            self::PendingConfiguration => 'Pendente de Parametrização Normativa',
            self::Exempt => 'Dispensado por Norma',
            self::NotApplicable => 'Sem Horário Noturno',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PendingConfiguration;
    }

    public function isCalculated(): bool
    {
        return $this === self::Calculated;
    }
}
