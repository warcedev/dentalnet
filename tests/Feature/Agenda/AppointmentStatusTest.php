<?php

require_once __DIR__.'/AgendaHelpers.php';

use App\Enums\AppointmentStatus;
use App\Models\Appointment;

beforeEach(function () {
    agendaSetup($this);
    $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this));
    $this->appointment = Appointment::firstOrFail();
});

function changeStatus(object $test, string $status, ?string $reason = null)
{
    return $test->actingAs($test->reception)->post(route('appointments.status', $test->appointment), array_filter(['status' => $status, 'reason' => $reason]));
}

it('sigue el flujo programada → confirmada → en curso → atendida', function () {
    changeStatus($this, 'confirmada')->assertSessionHasNoErrors();
    expect($this->appointment->fresh()->confirmed_at)->not->toBeNull();

    changeStatus($this, 'en_curso')->assertSessionHasNoErrors();
    changeStatus($this, 'atendida')->assertSessionHasNoErrors();

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Attended);
});

it('rechaza transiciones inválidas', function () {
    changeStatus($this, 'atendida')->assertSessionHasErrors(['status' => 'No se puede pasar una cita de "Programada" a "Atendida".']);
});

it('exige motivo para cancelar y lo guarda', function () {
    changeStatus($this, 'cancelada')->assertSessionHasErrors(['reason' => 'Indique el motivo de la cancelación.']);
    changeStatus($this, 'cancelada', 'Viaje')->assertSessionHasNoErrors();

    expect($this->appointment->fresh()->cancellation_reason)->toBe('Viaje')
        ->and($this->appointment->fresh()->cancelled_at)->not->toBeNull();
});

it('solo marca inasistencia después de la hora de la cita', function () {
    changeStatus($this, 'no_asistio')->assertSessionHasErrors(['status' => 'Solo se puede marcar la inasistencia después de la hora de la cita.']);

    $this->travelTo(agendaNow()->addDay()->setTime(11, 0));
    changeStatus($this, 'no_asistio')->assertSessionHasNoErrors();
});

it('una cita finalizada no se puede reprogramar', function () {
    changeStatus($this, 'cancelada', 'Viaje');

    $this->actingAs($this->reception)
        ->put(route('appointments.update', $this->appointment), array_diff_key(bookingPayload($this, ['start_time' => '12:00']), ['patient_id' => 1]))
        ->assertSessionHasErrors(['status' => 'Una cita cancelada no se puede modificar.']);
});

it('registra los cambios en la auditoría', function () {
    changeStatus($this, 'confirmada');

    $this->assertDatabaseHas('audit_logs', [
        'auditable_type' => Appointment::class,
        'auditable_id' => $this->appointment->id,
        'event' => 'updated',
    ]);
});

it('ofrece acciones con verbo y no ofrece la inasistencia antes de la hora', function () {
    $this->actingAs($this->reception)->get(route('agenda', ['fecha' => '2026-12-02']))
        ->assertInertia(fn ($page) => $page->where('appointments.0.transitions', [
            ['value' => 'confirmada', 'label' => 'Confirmar'],
            ['value' => 'en_curso', 'label' => 'Iniciar atención'],
            ['value' => 'cancelada', 'label' => 'Cancelar cita'],
        ]));

    $this->travelTo(agendaNow()->addDay()->setTime(10, 5));

    $this->actingAs($this->reception)->get(route('agenda', ['fecha' => '2026-12-02']))
        ->assertInertia(fn ($page) => $page->where('appointments.0.transitions.3', ['value' => 'no_asistio', 'label' => 'Marcar inasistencia']));
});
