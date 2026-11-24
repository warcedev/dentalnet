<?php

namespace App\Enums;

/**
 * Superficies dentarias. "lingual" se muestra como "palatina" en el maxilar
 * superior y "oclusal" como "incisal" en los dientes anteriores.
 */
enum ToothSurface: string
{
    case Vestibular = 'vestibular';
    case Lingual = 'lingual';
    case Mesial = 'mesial';
    case Distal = 'distal';
    case Occlusal = 'oclusal';

    public function labelFor(?int $toothCode): string
    {
        $upper = $toothCode !== null && in_array(intdiv($toothCode, 10), [1, 2, 5, 6], true);
        $anterior = $toothCode !== null && $toothCode % 10 <= 3;

        return match ($this) {
            self::Vestibular => 'Vestibular',
            self::Lingual => $upper ? 'Palatina' : 'Lingual',
            self::Mesial => 'Mesial',
            self::Distal => 'Distal',
            self::Occlusal => $anterior ? 'Incisal' : 'Oclusal',
        };
    }
}
