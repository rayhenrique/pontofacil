<?php

namespace App\Domain\Settlement\Enums;

enum HourDifferenceClassification: string
{
    case Tolerated = 'tolerated';
    case Justified = 'justified';
    case CompensableOvertime = 'compensable_overtime';
    case RemunerableOvertime = 'remunerable_overtime';
    case CompensableDeficit = 'compensable_deficit';
    case DeductibleDeficit = 'deductible_deficit';
    case PendingAnalysis = 'pending_analysis';

    public function label(): string
    {
        return match ($this) {
            self::Tolerated => 'Tolerada (Art. 58, § 1º CLT / Escala)',
            self::Justified => 'Justificada (Abonada / Atestado / Calendário)',
            self::CompensableOvertime => 'Hora Extra Compensável',
            self::RemunerableOvertime => 'Hora Extra Remunerável (Folha)',
            self::CompensableDeficit => 'Déficit Compensável (Atraso/Saída)',
            self::DeductibleDeficit => 'Déficit Descontável em Folha',
            self::PendingAnalysis => 'Pendente de Análise / Parametrização Jurídica',
        };
    }
}
