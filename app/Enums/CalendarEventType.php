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
            self::Holiday => 'bg-rose-50 text-rose-700 border border-rose-200',
            self::OptionalDay => 'bg-amber-50 text-amber-800 border border-amber-200',
            self::InstitutionalClosure => 'bg-purple-50 text-purple-700 border border-purple-200',
            self::SpecialWorkday => 'bg-sky-50 text-sky-700 border border-sky-200',
        };
    }

    public function dotColor(): string
    {
        return match ($this) {
            self::Holiday => 'bg-rose-500',
            self::OptionalDay => 'bg-amber-500',
            self::InstitutionalClosure => 'bg-purple-500',
            self::SpecialWorkday => 'bg-sky-500',
        };
    }
}
