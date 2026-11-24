<?php

namespace App\Enums;

enum TreatmentStatus: string
{
    case Proposed = 'propuesto';
    case Accepted = 'aceptado';
    case InProgress = 'en_curso';
    case Completed = 'completado';
    case Cancelled = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Propuesto',
            self::Accepted => 'Aceptado',
            self::InProgress => 'En curso',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Proposed => 'Volver a propuesto',
            self::Accepted => 'Aceptar',
            self::InProgress => 'Iniciar',
            self::Completed => 'Completar',
            self::Cancelled => 'Cancelar',
        };
    }

    /** Ítems que el paciente debe pagar (entran en el saldo). */
    public function isBillable(): bool
    {
        return in_array($this, [self::Accepted, self::InProgress, self::Completed], true);
    }

    /** @return list<self> */
    public static function billable(): array
    {
        return [self::Accepted, self::InProgress, self::Completed];
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Proposed => [self::Accepted, self::Cancelled],
            self::Accepted => [self::InProgress, self::Completed, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            // Regla 3: un completado no cambia más; se corrige con ajustes.
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
