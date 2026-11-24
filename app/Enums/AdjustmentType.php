<?php

namespace App\Enums;

/** Correcciones permitidas sobre un tratamiento completado (regla 3). */
enum AdjustmentType: string
{
    case Price = 'precio';
    case Warranty = 'garantia';
    case Note = 'nota';

    public function label(): string
    {
        return match ($this) {
            self::Price => 'Ajuste de precio',
            self::Warranty => 'Ajuste de garantía',
            self::Note => 'Aclaración',
        };
    }
}
