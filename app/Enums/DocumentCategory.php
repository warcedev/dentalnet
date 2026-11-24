<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Radiograph = 'radiografia';
    case IntraoralPhoto = 'foto_intraoral';
    case ExtraoralPhoto = 'foto_extraoral';
    case Study = 'estudio';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Radiograph => 'Radiografía',
            self::IntraoralPhoto => 'Foto intraoral',
            self::ExtraoralPhoto => 'Foto extraoral',
            self::Study => 'Estudio o informe',
            self::Other => 'Otro',
        };
    }
}
