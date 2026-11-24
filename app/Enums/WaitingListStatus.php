<?php

namespace App\Enums;

enum WaitingListStatus: string
{
    case Waiting = 'en_espera';
    case Scheduled = 'agendado';
    case Discarded = 'descartado';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'En espera',
            self::Scheduled => 'Agendado',
            self::Discarded => 'Descartado',
        };
    }
}
