<?php

namespace App\Enums;

enum SignerRelationship: string
{
    case Patient = 'paciente';
    case Parent = 'padre_madre';
    case LegalGuardian = 'tutor_legal';

    public function label(): string
    {
        return match ($this) {
            self::Patient => 'El propio paciente',
            self::Parent => 'Padre o madre',
            self::LegalGuardian => 'Tutor legal',
        };
    }
}
