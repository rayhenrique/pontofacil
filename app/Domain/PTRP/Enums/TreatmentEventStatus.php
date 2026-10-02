<?php

namespace App\Domain\PTRP\Enums;

enum TreatmentEventStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente de Análise',
            self::Approved => 'Aprovado',
            self::Rejected => 'Rejeitado',
        };
    }
}
