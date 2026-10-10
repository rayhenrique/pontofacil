<?php

namespace App\Enums;

enum ShiftOrigin: string
{
    case GeneratedCycle = 'generated_cycle';
    case ManualAssignment = 'manual_assignment';
    case Swap = 'swap';
    case Extraordinary = 'extraordinary';

    public function label(): string
    {
        return match ($this) {
            self::GeneratedCycle => 'Ciclo de Escala',
            self::ManualAssignment => 'Atribuição Manual',
            self::Swap => 'Troca / Permuta',
            self::Extraordinary => 'Convocação Extraordinária',
        };
    }
}
