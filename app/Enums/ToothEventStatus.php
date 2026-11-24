<?php

namespace App\Enums;

enum ToothEventStatus: string
{
    case Diagnosed = 'diagnosticado';
    case InTreatment = 'en_tratamiento';
    case Treated = 'tratado';
    case Failed = 'fallido';
    case FollowUp = 'en_seguimiento';

    public function label(): string
    {
        return match ($this) {
            self::Diagnosed => 'Diagnosticado',
            self::InTreatment => 'En tratamiento',
            self::Treated => 'Tratado',
            self::Failed => 'Fallido',
            self::FollowUp => 'En seguimiento',
        };
    }

    /** Estados que requieren fecha de tratamiento. */
    public function requiresTreatmentDate(): bool
    {
        return in_array($this, [self::Treated, self::Failed], true);
    }
}
