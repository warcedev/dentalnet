<?php

namespace App\Enums;

enum ReminderStatus: string
{
    case Pending = 'pendiente';
    case Queued = 'en_cola';
    case Sent = 'enviado';
    case Failed = 'fallido';
    case Cancelled = 'cancelado';
    case Skipped = 'omitido';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Queued => 'En cola',
            self::Sent => 'Enviado',
            self::Failed => 'Fallido',
            self::Cancelled => 'Cancelado',
            self::Skipped => 'Omitido',
        };
    }
}
