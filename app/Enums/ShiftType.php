<?php

namespace App\Enums;

enum ShiftType: string
{
    case Regular = 'regular';
    case Shift12x36 = 'shift_12x36';
    case Shift24x72 = 'shift_24x72';
    case Extraordinary = 'extraordinary';
    case Swapped = 'swapped';
    case OnCall = 'on_call';
    case OffDay = 'off_day';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Jornada Ordinária',
            self::Shift12x36 => 'Plantão 12×36',
            self::Shift24x72 => 'Plantão 24×72',
            self::Extraordinary => 'Plantão Extraordinário',
            self::Swapped => 'Troca de Plantão',
            self::OnCall => 'Sobreaviso / Prontidão',
            self::OffDay => 'Folga da Escala',
        };
    }

    public function isWorkShift(): bool
    {
        return $this !== self::OffDay;
    }
}
