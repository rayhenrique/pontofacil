<?php

namespace App\Domain\Settlement\Enums;

enum WorkTimeSettlementStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case AwaitingExecution = 'awaiting_execution';
    case Executed = 'executed';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Approved => 'Aprovada',
            self::AwaitingExecution => 'Aguardando Execução',
            self::Executed => 'Efetivada',
            self::Cancelled => 'Cancelada',
            self::Reversed => 'Estornada',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Executed, self::Cancelled, self::Reversed], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Approved, self::Cancelled], true),
            self::Approved => in_array($target, [self::AwaitingExecution, self::Executed, self::Cancelled], true),
            self::AwaitingExecution => in_array($target, [self::Executed, self::Cancelled], true),
            self::Executed => $target === self::Reversed,
            self::Cancelled, self::Reversed => false,
        };
    }
}
