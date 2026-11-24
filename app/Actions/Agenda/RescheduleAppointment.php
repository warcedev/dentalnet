<?php

namespace App\Actions\Agenda;

use App\Data\AppointmentData;
use App\Models\Appointment;
use App\Services\Agenda\AvailabilityChecker;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Validation\ValidationException;

class RescheduleAppointment
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly ReminderPlanner $reminders,
    ) {}

    public function handle(Appointment $appointment, AppointmentData $data): Appointment
    {
        if ($appointment->status->isFinal()) {
            throw ValidationException::withMessages(['status' => 'Una cita '.mb_strtolower($appointment->status->label()).' no se puede modificar.']);
        }

        BookingLock::run(function () use ($appointment, $data) {
            $errors = $this->availability->check(
                $data->startsAt, $data->endsAt(), $data->professionalId, $data->roomId, $appointment->patient_id, $appointment->id,
            );

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $appointment->update([
                'professional_id' => $data->professionalId,
                'room_id' => $data->roomId,
                'appointment_type_id' => $data->typeId,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt(),
                'notes' => $data->notes,
            ]);
        });

        $this->reminders->syncForAppointment($appointment->refresh());

        return $appointment;
    }
}
