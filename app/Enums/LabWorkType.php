<?php

namespace App\Enums;

enum LabWorkType: string
{
    case Crown = 'corona';
    case Bridge = 'puente';
    case RemovableProsthesis = 'protesis_removible';
    case CompleteDenture = 'protesis_total';
    case Splint = 'placa';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Crown => 'Corona',
            self::Bridge => 'Puente',
            self::RemovableProsthesis => 'Prótesis parcial removible',
            self::CompleteDenture => 'Prótesis total',
            self::Splint => 'Placa (miorrelajante / de contención)',
            self::Other => 'Otro trabajo',
        };
    }
}
