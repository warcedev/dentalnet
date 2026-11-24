<?php

namespace App\Enums;

enum ReminderChannelType: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Correo electrónico',
            self::WhatsApp => 'WhatsApp',
            self::Sms => 'SMS',
        };
    }
}
