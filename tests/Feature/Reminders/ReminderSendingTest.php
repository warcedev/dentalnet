<?php

require_once __DIR__.'/../Agenda/AgendaHelpers.php';

use App\Enums\ReminderStatus;
use App\Jobs\SendReminder;
use App\Mail\ReminderMail;
use App\Models\Appointment;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Reminders\Channels\ChannelRegistry;
use App\Services\Reminders\ReminderComposer;
use App\Services\Reminders\ReminderDispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    agendaSetup($this);
    $this->actingAs($this->reception)->post(route('appointments.store'), bookingPayload($this, ['date' => '2026-12-10', 'start_time' => '15:00']));
    $this->appointment = Appointment::firstOrFail();
    $this->reminder = Reminder::where('type', 'confirmacion_24h')->firstOrFail();
});

it('no envía antes de la fecha programada', function () {
    Bus::fake();

    expect(app(ReminderDispatcher::class)->dispatchDue())->toBe(0);
    Bus::assertNothingDispatched();
});

it('encola los vencidos una sola vez', function () {
    Bus::fake();
    $this->travelTo('2026-12-09 15:05:00');

    expect(app(ReminderDispatcher::class)->dispatchDue())->toBe(2)
        ->and(app(ReminderDispatcher::class)->dispatchDue())->toBe(0);

    Bus::assertDispatchedTimes(SendReminder::class, 2);
    expect($this->reminder->fresh()->status)->toBe(ReminderStatus::Queued);
});

it('envía el correo y registra el intento', function () {
    Mail::fake();
    $this->travelTo('2026-12-09 15:05:00');

    $this->artisan('reminders:run')->assertSuccessful();

    Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => $mail->hasTo('ana@ejemplo.com')
        && str_contains($mail->reminder->subject, 'Confirme su cita')
        && in_array('Tiene una cita el 10/12/2026 a las 15:00 con Dr. Uno.', $mail->reminder->lines, true));

    $reminder = $this->reminder->fresh();
    expect($reminder->status)->toBe(ReminderStatus::Sent)
        ->and($reminder->sent_at)->not->toBeNull()
        ->and($reminder->logs()->count())->toBe(1)
        ->and($reminder->logs()->first()->status)->toBe('enviado');
});

it('el correo no incluye datos clínicos', function () {
    $this->appointment->update(['notes' => 'Paciente con VIH, usar doble guante']);
    Mail::fake();
    $this->travelTo('2026-12-09 15:05:00');

    $this->artisan('reminders:run');

    Mail::assertSent(ReminderMail::class, fn (ReminderMail $mail) => ! str_contains($mail->render(), 'VIH'));
});

it('omite con motivo si el paciente no tiene correo', function () {
    Mail::fake();
    $this->patient->update(['email' => null]);
    $this->travelTo('2026-12-09 15:05:00');

    $this->artisan('reminders:run');

    Mail::assertNothingSent();
    expect($this->reminder->fresh()->status)->toBe(ReminderStatus::Skipped)
        ->and($this->reminder->fresh()->last_error)->toBe('El paciente no tiene correo electrónico registrado.');
});

it('no envía si la cita se canceló después de encolar', function () {
    Mail::fake();
    $this->travelTo('2026-12-09 15:05:00');
    $this->reminder->update(['status' => ReminderStatus::Queued]);
    Appointment::whereKey($this->appointment->id)->update(['status' => 'cancelada']);

    (new SendReminder($this->reminder->id))->handle(app(ChannelRegistry::class), app(ReminderComposer::class));

    Mail::assertNothingSent();
    expect($this->reminder->fresh()->status)->toBe(ReminderStatus::Skipped);
});

it('reintenta con espera y queda fallido tras el máximo de intentos', function () {
    config(['clinic.reminders.max_attempts' => 2]);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP no disponible'));
    $this->travelTo('2026-12-09 15:05:00');

    $this->artisan('reminders:run');
    $reminder = $this->reminder->fresh();
    expect($reminder->status)->toBe(ReminderStatus::Pending)
        ->and($reminder->attempts)->toBe(1)
        ->and($reminder->last_error)->toBe('SMTP no disponible')
        ->and($reminder->scheduled_for->format('H:i'))->toBe('15:10');

    $this->travelTo('2026-12-09 15:11:00');
    $this->artisan('reminders:run');

    $reminder = $reminder->fresh();
    expect($reminder->status)->toBe(ReminderStatus::Failed)
        ->and($reminder->attempts)->toBe(2)
        ->and($reminder->logs()->pluck('status')->all())->toBe(['reintento', 'fallido']);
});

it('un recordatorio fallido se puede reintentar desde la pantalla', function () {
    $this->reminder->update(['status' => ReminderStatus::Failed, 'attempts' => 3]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('reminders.retry', $this->reminder))->assertRedirect();

    expect($this->reminder->fresh()->status)->toBe(ReminderStatus::Pending)
        ->and($this->reminder->fresh()->attempts)->toBe(0);
});

it('solo administración y recepción gestionan recordatorios', function () {
    $this->actingAs($this->dentist)->get(route('reminders.index'))->assertForbidden();
    $this->actingAs($this->reception)->get(route('reminders.index'))->assertOk();
});
