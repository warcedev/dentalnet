<?php

namespace App\Actions\Agenda;

use App\Data\AppointmentData;
use App\Enums\AppointmentStatus;
use App\Enums\WaitingListStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Models\WaitingListEntry;
use App\Services\Agenda\AvailabilityChecker;
use App\Services\Reminders\ReminderPlanner;
use Illuminate\Validation\ValidationException;

class ScheduleAppointment
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly ReminderPlanner $reminders,
    ) {}

    public function handle(AppointmentData $data, User $actor): Appointment
    {
        $appointment = BookingLock::run(function () use ($data, $actor) {
            $errors = $this->availability->check($data->startsAt, $data->endsAt(), $data->professionalId, $data->roomId, $data->patientId);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $appointment = Appointment::create([
                'patient_id' => $data->patientId,
                'professional_id' => $data->professionalId,
                'room_id' => $data->roomId,
                'appointment_type_id' => $data->typeId,
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt(),
                'status' => AppointmentStatus::Scheduled,
                'notes' => $data->notes,
                'created_by' => $actor->id,
            ]);

            if ($data->waitingListEntryId !== null) {
                WaitingListEntry::query()
                    ->whereKey($data->waitingListEntryId)
                    ->where('patient_id', $data->patientId)
                    ->where('status', WaitingListStatus::Waiting)
                    ->first()
                    ?->update(['status' => WaitingListStatus::Scheduled, 'appointment_id' => $appointment->id]);
            }

            return $appointment;
        });

        $this->reminders->syncForAppointment($appointment);

        return $appointment;
    }
}
