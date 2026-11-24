<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Scheduled = 'programada';
    case Confirmed = 'confirmada';
    case InProgress = 'en_curso';
    case Attended = 'atendida';
    case Cancelled = 'cancelada';
    case NoShow = 'no_asistio';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programada',
            self::Confirmed => 'Confirmada',
            self::InProgress => 'En curso',
            self::Attended => 'Atendida',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No asistió',
        };
    }

    /** Texto del botón que lleva a este estado (un verbo). */
    public function actionLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Volver a programada',
            self::Confirmed => 'Confirmar',
            self::InProgress => 'Iniciar atención',
            self::Attended => 'Marcar atendida',
            self::Cancelled => 'Cancelar cita',
            self::NoShow => 'Marcar inasistencia',
        };
    }

    /** Estados que ocupan el horario (los demás lo liberan). */
    public function occupiesSlot(): bool
    {
        return ! in_array($this, [self::Cancelled, self::NoShow], true);
    }

    /** @return list<self> */
    public static function occupying(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->occupiesSlot()));
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Attended, self::Cancelled, self::NoShow], true);
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::InProgress, self::Cancelled, self::NoShow],
            self::Confirmed => [self::InProgress, self::Cancelled, self::NoShow],
            self::InProgress => [self::Attended],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
