<?php

namespace App\Services\Agenda;

use App\Models\Appointment;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;

/**
 * Regla de negocio 2: una cita no puede superponerse con otra del mismo
 * profesional, del mismo consultorio ni del mismo paciente, ni caer en un
 * bloqueo de horario o fuera del horario de atención.
 */
class AvailabilityChecker
{
    /**
     * @return array<string, string> Errores por campo (vacío si está disponible)
     */
    public function check(
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $professionalId,
        int $roomId,
        int $patientId,
        ?int $ignoreAppointmentId = null,
    ): array {
        $errors = $this->checkOpeningHours($start, $end);

        if ($errors !== []) {
            return $errors;
        }

        $overlapping = fn () => Appointment::query()
            ->overlapping($start, $end)
            ->when($ignoreAppointmentId, fn ($q, $id) => $q->whereKeyNot($id));

        $professionalClash = $overlapping()->where('professional_id', $professionalId)->with('patient:id,first_name,last_name')->first();

        if ($professionalClash !== null) {
            $errors['professional_id'] = sprintf(
                'El profesional ya tiene una cita de %s a %s (%s).',
                $professionalClash->starts_at->format('H:i'),
                $professionalClash->ends_at->format('H:i'),
                $professionalClash->patient->full_name,
            );
        }

        $roomClash = $overlapping()->where('room_id', $roomId)->first();

        if ($roomClash !== null) {
            $errors['room_id'] = sprintf(
                'El consultorio está ocupado de %s a %s.',
                $roomClash->starts_at->format('H:i'),
                $roomClash->ends_at->format('H:i'),
            );
        }

        $patientClash = $overlapping()->where('patient_id', $patientId)->first();

        if ($patientClash !== null) {
            $errors['patient_id'] = sprintf(
                'El paciente ya tiene una cita de %s a %s.',
                $patientClash->starts_at->format('H:i'),
                $patientClash->ends_at->format('H:i'),
            );
        }

        $block = ScheduleBlock::query()->overlapping($start, $end)->affecting($professionalId, $roomId)->first();

        if ($block !== null) {
            $errors['starts_at'] = sprintf(
                'El horario está bloqueado de %s a %s: %s.',
                $block->starts_at->format('d/m H:i'),
                $block->ends_at->format('d/m H:i'),
                $block->reason,
            );
        }

        return $errors;
    }

    /** @return array<string, string> */
    public function checkOpeningHours(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $config = config('clinic.agenda');

        if ($end->lte($start)) {
            return ['duration_minutes' => 'La duración debe ser mayor que cero.'];
        }

        if (! $start->isSameDay($end)) {
            return ['duration_minutes' => 'La cita debe empezar y terminar el mismo día.'];
        }

        if (! in_array($start->dayOfWeekIso, (array) $config['working_days'], true)) {
            return ['starts_at' => 'El consultorio no atiende ese día.'];
        }

        $opens = $start->setTimeFromTimeString((string) $config['opens_at']);
        $closes = $start->setTimeFromTimeString((string) $config['closes_at']);

        if ($start->lt($opens) || $end->gt($closes)) {
            return ['starts_at' => sprintf('La cita debe estar dentro del horario de atención (%s a %s).', $config['opens_at'], $config['closes_at'])];
        }

        return [];
    }
}
