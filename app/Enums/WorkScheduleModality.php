<?php

namespace App\Enums;

enum WorkScheduleModality: string
{
    case FixedWeekly = 'fixed_weekly';
    case AlternatingWeekly = 'alternating_weekly';
    case FiveByTwo = '5x2';
    case SixByOne = '6x1';
    case TwelveByThirtySix = '12x36';
    case TwentyFourBySeventyTwo = '24x72';
    case CustomCycle = 'custom_cycle';
    case VariableShifts = 'variable_shifts';

    public function label(): string
    {
        return match ($this) {
            self::FixedWeekly => 'Semanal Fixa',
            self::AlternatingWeekly => 'Semanal com Dias Alternados',
            self::FiveByTwo => '5×2 (5 dias trabalho / 2 folga)',
            self::SixByOne => '6×1 (6 dias trabalho / 1 folga)',
            self::TwelveByThirtySix => '12×36 (12h trabalho / 36h descanso)',
            self::TwentyFourBySeventyTwo => '24×72 (Plantão 24h / 72h descanso)',
            self::CustomCycle => 'Ciclo Personalizado',
            self::VariableShifts => 'Escala Variável por Plantões',
        };
    }

    public function requiresSpecialAuthorization(): bool
    {
        return in_array($this, [
            self::TwelveByThirtySix,
            self::TwentyFourBySeventyTwo,
        ], true);
    }

    public function defaultCycleDays(): int
    {
        return match ($this) {
            self::FixedWeekly, self::AlternatingWeekly, self::FiveByTwo, self::SixByOne => 7,
            self::TwelveByThirtySix => 2,
            self::TwentyFourBySeventyTwo => 4,
            self::CustomCycle, self::VariableShifts => 30,
        };
    }
}
