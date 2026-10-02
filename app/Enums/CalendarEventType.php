<?php

namespace App\Enums;

/**
 * 20.18.2 — Tipos de evento do calendário laboral.
 *
 * HOLIDAY: Feriado legal (federal, estadual ou municipal).
 * OPTIONAL_DAY: Ponto facultativo — NÃO tratado automaticamente como feriado.
 * INSTITUTIONAL_CLOSURE: Suspensão administrativa da empresa/órgão (recesso, etc.).
 * SPECIAL_WORKDAY: Dia com funcionamento excepcional (expediente parcial, plantão, etc.).
 */
enum CalendarEventType: string
{
    case Holiday = 'holiday';
    case OptionalDay = 'optional_day';
    case InstitutionalClosure = 'institutional_closure';
    case SpecialWorkday = 'special_workday';

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Feriado',
            self::OptionalDay => 'Ponto Facultativo',
            self::InstitutionalClosure => 'Recesso Administrativo',
            self::SpecialWorkday => 'Expediente Especial',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Holiday => 'bg-red-500/10 text-red-400 border border-red-500/30',
            self::OptionalDay => 'bg-amber-500/10 text-amber-400 border border-amber-500/30',
            self::InstitutionalClosure => 'bg-purple-500/10 text-purple-400 border border-purple-500/30',
            self::SpecialWorkday => 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/30',
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Holiday => 'bg-red-500',
            self::OptionalDay => 'bg-amber-500',
            self::InstitutionalClosure => 'bg-purple-500',
            self::SpecialWorkday => 'bg-cyan-500',
        };
    }
}
