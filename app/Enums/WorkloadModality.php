<?php

namespace App\Enums;

enum WorkloadModality: string
{
    case Fixed = 'fixed';
    case VariableBySchedule = 'variable_by_schedule';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Carga Fixa',
            self::VariableBySchedule => 'Variável por Escala / Plantões',
        };
    }
}
