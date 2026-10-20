<?php

namespace App\Domain\PTRP\Enums;

enum TreatmentEventStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente de Análise',
            self::Approved => 'Aprovado',
            self::Rejected => 'Rejeitado',
            self::Cancelled => 'Cancelado',
            self::Revoked => 'Revertido',
        };
    }
}
