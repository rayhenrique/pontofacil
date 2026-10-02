<?php

namespace App\Enums;

/**
 * 20.18.4 — Comportamento sobre a jornada para um evento de calendário.
 *
 * Fundamental: o work_behavior é independente do type.
 * Um OPTIONAL_DAY pode ter qualquer work_behavior — o Admin/RH decide.
 */
enum WorkBehavior: string
{
    case NoWorkExpected = 'no_work_expected';
    case NormalWorkday = 'normal_workday';
    case ReducedWorkday = 'reduced_workday';
    case OptionalNoWork = 'optional_no_work';
    case OptionalWithCompensation = 'optional_with_compensation';

    public function label(): string
    {
        return match ($this) {
            self::NoWorkExpected => 'Sem expediente',
            self::NormalWorkday => 'Expediente normal',
            self::ReducedWorkday => 'Expediente reduzido',
            self::OptionalNoWork => 'Sem expediente (facultativo)',
            self::OptionalWithCompensation => 'Sem expediente com compensação',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NoWorkExpected => 'Não há jornada prevista; ausência não gera falta.',
            self::NormalWorkday => 'Jornada normal conforme escala de trabalho.',
            self::ReducedWorkday => 'Expediente parcial conforme horários configurados.',
            self::OptionalNoWork => 'Dispensa de expediente; ausência não gera falta.',
            self::OptionalWithCompensation => 'Dispensa de expediente com obrigação de compensação futura.',
        };
    }

    /**
     * Indica se a jornada esperada deve ser zerada pelo motor.
     */
    public function suspendsWork(): bool
    {
        return match ($this) {
            self::NoWorkExpected, self::OptionalNoWork, self::OptionalWithCompensation => true,
            self::NormalWorkday, self::ReducedWorkday => false,
        };
    }
}
