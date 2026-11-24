<?php

namespace App\Http\Presenters;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;

class AppointmentPresenter
{
    /**
     * Las fechas van como hora local sin zona ("2026-05-01 10:30") para que el
     * navegador no las corra según la zona horaria del equipo.
     *
     * @param  array<int, bool>  $patientsWithAllergies
     * @return array<string, mixed>
     */
    public function present(Appointment $a, array $patientsWithAllergies = []): array
    {
        return [
            'id' => $a->id,
            'starts_at' => $a->starts_at->format('Y-m-d H:i'),
            'ends_at' => $a->ends_at->format('Y-m-d H:i'),
            'duration' => (int) $a->starts_at->diffInMinutes($a->ends_at),
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            // Solo las acciones posibles ahora: la inasistencia, recién después de la hora.
            'transitions' => array_values(array_map(
                fn (AppointmentStatus $s) => ['value' => $s->value, 'label' => $s->actionLabel()],
                array_filter(
                    $a->status->allowedTransitions(),
                    fn (AppointmentStatus $s) => $s !== AppointmentStatus::NoShow || $a->starts_at->isPast(),
                ),
            )),
            'notes' => $a->notes,
            'cancellation_reason' => $a->cancellation_reason,
            'patient' => [
                'id' => $a->patient_id,
                'name' => $a->patient->full_name,
                'phone' => $a->patient->phone,
                'has_allergies' => $patientsWithAllergies[$a->patient_id] ?? false,
            ],
            'professional' => ['id' => $a->professional_id, 'name' => $a->professional->name],
            'room' => ['id' => $a->room_id, 'name' => $a->room->name],
            'type' => ['id' => $a->appointment_type_id, 'name' => $a->type->name, 'color' => $a->type->color],
        ];
    }
}
