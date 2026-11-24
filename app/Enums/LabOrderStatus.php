<?php

namespace App\Enums;

enum LabOrderStatus: string
{
    case Sent = 'enviado';
    case Received = 'recibido';
    case Delivered = 'colocado';
    case Cancelled = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Enviado al laboratorio',
            self::Received => 'Recibido del laboratorio',
            self::Delivered => 'Colocado al paciente',
            self::Cancelled => 'Cancelado',
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Sent => 'Marcar enviado',
            self::Received => 'Marcar recibido',
            self::Delivered => 'Marcar colocado',
            self::Cancelled => 'Cancelar',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Sent => [self::Received, self::Cancelled],
            self::Received => [self::Delivered],
            default => [],
        };
    }
}
