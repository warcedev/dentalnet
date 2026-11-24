<?php

namespace App\Enums;

enum ToothCondition: string
{
    // Superficie
    case Caries = 'caries';
    case Restoration = 'restauracion';
    case Sealant = 'sellante';
    case Fracture = 'fractura';

    // Diente completo
    case Missing = 'ausente';
    case Extraction = 'extraccion';
    case Implant = 'implante';
    case Crown = 'corona';
    case Bridge = 'puente';
    case RootCanal = 'endodoncia';
    case Prosthesis = 'protesis';
    case Post = 'perno';

    // Boca completa
    case Cleaning = 'limpieza';

    public function scope(): ToothConditionScope
    {
        return match ($this) {
            self::Caries, self::Restoration, self::Sealant, self::Fracture => ToothConditionScope::Surface,
            self::Cleaning => ToothConditionScope::Mouth,
            default => ToothConditionScope::Tooth,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Caries => 'Caries',
            self::Restoration => 'Restauración / obturación',
            self::Sealant => 'Sellante',
            self::Fracture => 'Fractura',
            self::Missing => 'Ausente',
            self::Extraction => 'Extracción',
            self::Implant => 'Implante',
            self::Crown => 'Corona',
            self::Bridge => 'Puente',
            self::RootCanal => 'Endodoncia',
            self::Prosthesis => 'Prótesis',
            self::Post => 'Perno',
            self::Cleaning => 'Limpieza (profilaxis)',
        };
    }

    /** Condiciones que, ya tratadas, dejan al diente sin corona natural visible. */
    public function removesTooth(): bool
    {
        return in_array($this, [self::Missing, self::Extraction], true);
    }
}
