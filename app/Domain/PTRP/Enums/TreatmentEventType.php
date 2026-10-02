<?php

namespace App\Domain\PTRP\Enums;

enum TreatmentEventType: string
{
    case ManualPunchAdded = 'manual_punch_added';
    case PunchDisregarded = 'punch_disregarded';
    case AbsenceAdded = 'absence_added';
    case AbsenceJustified = 'absence_justified';
    case ClassificationOverride = 'classification_override';

    public function label(): string
    {
        return match ($this) {
            self::ManualPunchAdded => 'Inclusão de Batida Manual',
            self::PunchDisregarded => 'Desconsideração de Marcação',
            self::AbsenceAdded => 'Registro de Falta',
            self::AbsenceJustified => 'Abono de Ausência / Atestado',
            self::ClassificationOverride => 'Reclassificação de Batida',
        };
    }
}
