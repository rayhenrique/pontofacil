<?php

namespace App\Enums;

enum LegalRegime: string
{
    case CLT = 'clt';
    case FederalStatutory = 'federal_statutory';
    case StateStatutory = 'state_statutory';
    case MunicipalStatutory = 'municipal_statutory';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CLT => 'CLT (Consolidação das Leis do Trabalho)',
            self::FederalStatutory => 'Estatutário Federal',
            self::StateStatutory => 'Estatutário Estadual',
            self::MunicipalStatutory => 'Estatutário Municipal',
            self::Other => 'Outro Regime Específico',
        };
    }

    public function isStatutory(): bool
    {
        return in_array($this, [
            self::FederalStatutory,
            self::StateStatutory,
            self::MunicipalStatutory,
        ], true);
    }

    public function requiresNormativeDetails(): bool
    {
        return in_array($this, [
            self::StateStatutory,
            self::MunicipalStatutory,
            self::Other,
        ], true);
    }

    public function defaultJurisdiction(): ?string
    {
        return match ($this) {
            self::FederalStatutory => 'federal',
            self::StateStatutory => 'state',
            self::MunicipalStatutory => 'municipal',
            self::CLT => 'federal',
            self::Other => 'other',
        };
    }
}
