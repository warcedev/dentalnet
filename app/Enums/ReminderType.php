<?php

namespace App\Enums;

enum ReminderType: string
{
    case UpcomingAppointment = 'proxima_cita';
    case Confirmation = 'confirmacion_24h';
    case Cleaning = 'limpieza';
    case TreatmentControl = 'control_tratamiento';
    case Birthday = 'cumpleanos';
    case WarrantyControl = 'control_garantia';

    public function label(): string
    {
        return match ($this) {
            self::UpcomingAppointment => 'Aviso de próxima cita',
            self::Confirmation => 'Confirmación 24 h antes',
            self::Cleaning => 'Limpieza semestral',
            self::TreatmentControl => 'Control de tratamiento',
            self::Birthday => 'Cumpleaños',
            self::WarrantyControl => 'Control antes de vencer la garantía',
        };
    }
}
