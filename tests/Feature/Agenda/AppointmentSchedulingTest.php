<?php

require_once __DIR__.'/AgendaHelpers.php';

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Models\WaitingListEntry;

beforeEach(fn () => agendaSetup($this));

it('recepción agenda una cita y se calcula la hora de fin', function () {
    $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this))->assertSessionHasNoErrors();

    $appointment = Appointment::firstOrFail();
    expect($appointment->starts_at->format('Y-m-d H:i'))->toBe('2026-12-02 10:00')
        ->and($appointment->ends_at->format('H:i'))->toBe('10:30')
        ->and($appointment->status)->toBe(AppointmentStatus::Scheduled)
        ->and($appointment->created_by)->toBe($this->reception->id);
});

describe('regla 2: sin solapamientos', function () {
    beforeEach(function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this));
    });

    it('rechaza otra cita del mismo profesional que se superpone', function () {
        $other = Patient::factory()->create();

        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, [
            'patient_id' => $other->id, 'room_id' => $this->otherRoom->id, 'start_time' => '10:15',
        ]))->assertSessionHasErrors(['professional_id']);

        expect(session('errors')->first('professional_id'))->toContain('El profesional ya tiene una cita de 10:00 a 10:30');
    });

    it('rechaza otra cita en el mismo consultorio que se superpone', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, [
            'patient_id' => Patient::factory()->create()->id, 'professional_id' => $this->otherDentist->id, 'start_time' => '09:45',
        ]))->assertSessionHasErrors(['room_id' => 'El consultorio está ocupado de 10:00 a 10:30.']);
    });

    it('rechaza una cita superpuesta del mismo paciente', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, [
            'professional_id' => $this->otherDentist->id, 'room_id' => $this->otherRoom->id, 'start_time' => '10:20',
        ]))->assertSessionHasErrors(['patient_id']);
    });

    it('permite citas consecutivas (una termina cuando empieza la otra)', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, [
            'patient_id' => Patient::factory()->create()->id, 'start_time' => '10:30',
        ]))->assertSessionHasNoErrors();

        expect(Appointment::count())->toBe(2);
    });

    it('permite el mismo horario con otro profesional y otro consultorio', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, [
            'patient_id' => Patient::factory()->create()->id, 'professional_id' => $this->otherDentist->id, 'room_id' => $this->otherRoom->id,
        ]))->assertSessionHasNoErrors();
    });

    it('una cita cancelada libera el horario', function () {
        Appointment::firstOrFail()->update(['status' => AppointmentStatus::Cancelled]);

        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, [
            'patient_id' => Patient::factory()->create()->id,
        ]))->assertSessionHasNoErrors();
    });

    it('al reprogramar no choca consigo misma pero sí con otras', function () {
        $first = Appointment::firstOrFail();
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, [
            'patient_id' => Patient::factory()->create()->id, 'start_time' => '11:00',
        ]));

        $this->actingAs($this->reception)->put(route('appointments.update', $first), array_diff_key(bookingPayload($this, ['start_time' => '10:15']), ['patient_id' => 1]))
            ->assertSessionHasNoErrors();
        expect($first->fresh()->starts_at->format('H:i'))->toBe('10:15');

        $this->actingAs($this->reception)->put(route('appointments.update', $first), array_diff_key(bookingPayload($this, ['start_time' => '10:45']), ['patient_id' => 1]))
            ->assertSessionHasErrors('professional_id');
    });
});

describe('horarios y bloqueos', function () {
    it('rechaza citas fuera del horario de atención', function (string $time, int $duration) {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, ['start_time' => $time, 'duration_minutes' => $duration]))
            ->assertSessionHasErrors(['starts_at' => 'La cita debe estar dentro del horario de atención (07:00 a 20:00).']);
    })->with([
        'antes de abrir' => ['06:30', 30],
        'termina después de cerrar' => ['19:45', 30],
    ]);

    it('rechaza domingos', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, ['date' => '2026-12-06']))
            ->assertSessionHasErrors(['starts_at' => 'El consultorio no atiende ese día.']);
    });

    it('rechaza horarios que ya pasaron', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, ['date' => '2026-11-30']))
            ->assertSessionHasErrors(['start_time' => 'No se puede agendar en un horario que ya pasó.']);
    });

    it('respeta los bloqueos del profesional, del consultorio y generales', function (string $scope) {
        ScheduleBlock::create([
            'professional_id' => $scope === 'profesional' ? $this->dentist->id : null,
            'room_id' => $scope === 'consultorio' ? $this->room->id : null,
            'starts_at' => '2026-12-02 09:00', 'ends_at' => '2026-12-02 12:00', 'reason' => 'Capacitación',
        ]);

        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this))
            ->assertSessionHasErrors(['starts_at' => 'El horario está bloqueado de 02/12 09:00 a 02/12 12:00: Capacitación.']);
    })->with(['profesional', 'consultorio', 'general']);

    it('no permite bloquear un horario que ya tiene citas', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this));

        $this->actingAs($this->reception)->post(route('blocks.store'), [
            'professional_id' => $this->dentist->id, 'starts_at' => '2026-12-02T09:00', 'ends_at' => '2026-12-02T11:00', 'reason' => 'Vacaciones',
        ])->assertSessionHasErrors(['starts_at' => 'Hay 1 cita en ese horario. Reprográmela o cancélela antes de bloquear.']);
    });

    it('solo acepta odontólogos activos como profesional', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, ['professional_id' => $this->reception->id]))
            ->assertSessionHasErrors(['professional_id' => 'Seleccione un odontólogo activo.']);
    });
});

describe('lista de espera y permisos', function () {
    it('al agendar desde la lista de espera, el paciente sale de la lista', function () {
        $entry = WaitingListEntry::create(['patient_id' => $this->patient->id, 'status' => 'en_espera']);

        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, ['waiting_list_entry_id' => $entry->id]));

        expect($entry->fresh()->status->value)->toBe('agendado')
            ->and($entry->fresh()->appointment_id)->toBe(Appointment::firstOrFail()->id);
    });

    it('un usuario sin rol no puede ver ni agendar', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('agenda'))->assertForbidden();
        $this->actingAs($user)->post(route('appointments.store'), bookingPayload($this))->assertForbidden();
    });

    it('el odontólogo también puede agendar', function () {
        $this->actingAs($this->dentist)->post(route('appointments.store'), bookingPayload($this))->assertSessionHasNoErrors();
    });

    it('las citas no se borran', function () {
        $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this));

        expect($this->reception->can('delete', Appointment::firstOrFail()))->toBeFalse();
    });
});
