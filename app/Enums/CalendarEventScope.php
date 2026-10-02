<?php

namespace App\Enums;

/**
 * 20.18.3 — Escopo territorial do evento de calendário.
 *
 * Define a abrangência geográfica/organizacional do evento.
 * A resolução hierárquica é: ESTABLISHMENT > MUNICIPAL > STATE > NATIONAL.
 */
enum CalendarEventScope: string
{
    case National = 'national';
    case State = 'state';
    case Municipal = 'municipal';
    case Establishment = 'establishment';

    public function label(): string
    {
        return match ($this) {
            self::National => 'Nacional',
            self::State => 'Estadual',
            self::Municipal => 'Municipal',
            self::Establishment => 'Estabelecimento',
        };
    }

    /**
     * Prioridade para resolução hierárquica (maior = mais específico).
     */
    public function priority(): int
    {
        return match ($this) {
            self::National => 1,
            self::State => 2,
            self::Municipal => 3,
            self::Establishment => 4,
        };
    }
}
