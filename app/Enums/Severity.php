<?php

namespace App\Enums;

enum Severity: string
{
    case Mild = 'leve';
    case Moderate = 'moderada';
    case Severe = 'grave';

    public function label(): string
    {
        return match ($this) {
            self::Mild => 'Leve',
            self::Moderate => 'Moderada',
            self::Severe => 'Grave',
        };
    }
}
