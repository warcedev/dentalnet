<?php

namespace App\Enums;

enum PrescriptionType: string
{
    case Prescription = 'receta';
    case Instructions = 'indicaciones';

    public function label(): string
    {
        return match ($this) {
            self::Prescription => 'Receta',
            self::Instructions => 'Indicaciones post-operatorias',
        };
    }
}
