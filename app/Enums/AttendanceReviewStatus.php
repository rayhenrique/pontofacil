<?php

namespace App\Enums;

enum AttendanceReviewStatus: string
{
    case Pending = 'pending';
    case WithOccurrences = 'with_occurrences';
    case Reviewed = 'reviewed';
    case ReviewedWithReservation = 'reviewed_with_reservation';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando Conferência',
            self::WithOccurrences => 'Com Ocorrência',
            self::Reviewed => 'Conferido',
            self::ReviewedWithReservation => 'Conferido com Ressalva',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700 border-amber-200',
            self::WithOccurrences => 'bg-rose-50 text-rose-700 border-rose-200',
            self::Reviewed => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::ReviewedWithReservation => 'bg-blue-50 text-blue-700 border-blue-200',
        };
    }

    public function badgeClass(): string
    {
        return $this->badgeClasses();
    }

    public function isReviewed(): bool
    {
        return in_array($this, [self::Reviewed, self::ReviewedWithReservation], true);
    }
}
