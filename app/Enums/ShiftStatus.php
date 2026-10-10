<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Swapped = 'swapped';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programado',
            self::Confirmed => 'Confirmado',
            self::Completed => 'Realizado',
            self::Cancelled => 'Cancelado',
            self::Swapped => 'Permutado',
            self::Absent => 'Ausente',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed, self::Completed], true);
    }
}
