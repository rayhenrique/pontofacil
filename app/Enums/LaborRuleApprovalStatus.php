<?php

namespace App\Enums;

enum LaborRuleApprovalStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho / Em Análise',
            self::Approved => 'Aprovado e Vigente',
            self::Revoked => 'Revogado',
        };
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }
}
