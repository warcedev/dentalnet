<?php

require_once __DIR__.'/AgendaHelpers.php';

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    agendaSetup($this);
    $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this));
});

it('muestra el día con las citas en hora local', function () {
    $this->actingAs($this->reception)->get(route('agenda', ['fecha' => '2026-12-02']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('agenda/index')
            ->where('view', 'dia')
            ->has('appointments', 1)
            ->where('appointments.0.starts_at', '2026-12-02 10:00')
            ->where('appointments.0.patient.name', $this->patient->full_name)
            ->has('professionals', 2)
            ->where('can_manage', true));
});

it('la vista semanal filtra por profesional', function () {
    $this->actingAs($this->reception)->get(route('agenda', ['vista' => 'semana', 'fecha' => '2026-12-02', 'profesional' => $this->otherDentist->id]))
        ->assertInertia(fn (Assert $page) => $page->has('appointments', 0)->where('range.from', '2026-11-30')->where('range.to', '2026-12-06'));
});

it('la vista mensual cubre semanas completas', function () {
    $this->actingAs($this->reception)->get(route('agenda', ['vista' => 'mes', 'fecha' => '2026-12-15']))
        ->assertInertia(fn (Assert $page) => $page->where('range.from', '2026-11-30')->where('range.to', '2027-01-03')->has('appointments', 1));
});

it('busca pacientes con una recarga parcial', function () {
    $this->actingAs($this->reception)
        ->get(route('agenda', ['buscar_paciente' => mb_substr($this->patient->last_name, 0, 5)]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'agenda/index',
            'X-Inertia-Partial-Data' => 'patient_options',
        ])
        ->assertJsonPath('props.patient_options.0.id', $this->patient->id);
});

it('precarga el paciente al venir desde su ficha', function () {
    $this->actingAs($this->reception)->get(route('agenda', ['paciente' => $this->patient->id]))
        ->assertInertia(fn (Assert $page) => $page->where('prefill.patient.id', $this->patient->id));
});

it('la ficha del paciente muestra sus próximas citas', function () {
    $this->actingAs($this->reception)->get(route('patients.show', $this->patient))
        ->assertInertia(fn (Assert $page) => $page->has('upcoming', 1)->where('can_book', true));
});

it('el inicio muestra al odontólogo solo sus citas del día', function () {
    $this->travelTo(agendaNow()->addDay()->setTime(7, 30));
    $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, [
        'patient_id' => Patient::factory()->create()->id, 'professional_id' => $this->otherDentist->id,
        'room_id' => $this->otherRoom->id, 'start_time' => '11:00',
    ]));

    $this->actingAs($this->dentist)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('only_mine', true)->has('today_appointments', 1));
    $this->actingAs($this->reception)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('only_mine', false)->has('today_appointments', 2));
});

it('solo el administrador configura tipos y consultorios', function () {
    $this->actingAs($this->reception)->get(route('admin.agenda-settings'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.agenda-settings'))->assertOk();
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.appointment-types.store'), ['name' => 'Blanqueamiento', 'duration_minutes' => 60, 'color' => '#123456'])
        ->assertSessionHasNoErrors();
});

it('recepción y odontólogos ven la agenda; el usuario sin rol no', function () {
    $this->actingAs($this->dentist)->get(route('agenda'))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('agenda'))->assertForbidden();
    expect(Appointment::count())->toBe(1);
});
