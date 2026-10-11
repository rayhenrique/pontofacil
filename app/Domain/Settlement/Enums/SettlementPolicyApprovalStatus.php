<?php

namespace App\Domain\Settlement\Enums;

enum SettlementPolicyApprovalStatus: string
{
    case Approved = 'approved';
    case PendingApproval = 'pending_approval';
    case Draft = 'draft';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Aprovada e Vigente',
            self::PendingApproval => 'Aguardando Aprovação',
            self::Draft => 'Rascunho',
            self::Rejected => 'Rejeitada / Não Homologada',
        };
    }
}
