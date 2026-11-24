<?php

namespace App\Actions\Agenda;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Validation\ValidationException;

class ChangeAppointmentStatus
{
    public function __construct(private readonly ReminderPlanner $reminders) {}

    public function handle(Appointment $appointment, AppointmentStatus $target, ?string $reason = null): Appointment
    {
        $current = $appointment->status;

        if (! $current->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => sprintf('No se puede pasar una cita de "%s" a "%s".', $current->label(), $target->label()),
            ]);
        }

        if ($target === AppointmentStatus::NoShow && $appointment->starts_at->isFuture()) {
            throw ValidationException::withMessages(['status' => 'Solo se puede marcar la inasistencia después de la hora de la cita.']);
        }

        if ($target === AppointmentStatus::Cancelled && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Indique el motivo de la cancelación.']);
        }

        $appointment->update(array_filter([
            'status' => $target,
            'confirmed_at' => $target === AppointmentStatus::Confirmed ? now() : null,
            'cancelled_at' => $target === AppointmentStatus::Cancelled ? now() : null,
            'cancellation_reason' => $target === AppointmentStatus::Cancelled ? $reason : null,
        ], fn ($v) => $v !== null));

        $this->reminders->syncForAppointment($appointment);

        return $appointment;
    }
}
