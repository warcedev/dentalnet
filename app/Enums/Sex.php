<?php

namespace App\Enums;

/** Sexo registrado (dato clínico). */
enum Sex: string
{
    case Female = 'f';
    case Male = 'm';
    case Other = 'x';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Femenino',
            self::Male => 'Masculino',
            self::Other => 'Otro / no especifica',
        };
    }
}
