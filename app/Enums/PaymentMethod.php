<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'efectivo';
    case Transfer = 'transferencia';
    case DebitCard = 'tarjeta_debito';
    case CreditCard = 'tarjeta_credito';
    case Qr = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Transfer => 'Transferencia',
            self::DebitCard => 'Tarjeta de débito',
            self::CreditCard => 'Tarjeta de crédito',
            self::Qr => 'Pago con QR',
        };
    }
}
