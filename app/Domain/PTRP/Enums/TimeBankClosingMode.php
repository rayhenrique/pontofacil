<?php

namespace App\Domain\PTRP\Enums;

enum TimeBankClosingMode: string
{
    case CarryOver = 'CARRY_OVER';
    case MonthlyReset = 'MONTHLY_RESET';

    public function label(): string
    {
        return match ($this) {
            self::CarryOver => 'Acumular saldo para o mês seguinte',
            self::MonthlyReset => 'Zerar saldo ao fechar cada mês',
        };
    }
}
