<?php

namespace App\Enums;

enum DocumentType: string
{
    case Ci = 'ci';
    case Passport = 'pasaporte';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Ci => 'Cédula de identidad',
            self::Passport => 'Pasaporte',
            self::Other => 'Otro documento',
        };
    }
}
