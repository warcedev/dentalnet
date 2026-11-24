<?php

namespace App\Enums;

enum AllergyType: string
{
    case Drug = 'medicamento';
    case Anesthetic = 'anestesico';
    case Latex = 'latex';
    case Material = 'material';
    case Food = 'alimento';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Drug => 'Medicamento',
            self::Anesthetic => 'Anestésico',
            self::Latex => 'Látex',
            self::Material => 'Material odontológico',
            self::Food => 'Alimento',
            self::Other => 'Otro',
        };
    }
}
