<?php

require_once __DIR__.'/../Billing/BillingHelpers.php';

use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Payment;
use App\Models\Prescription;
use App\Notifications\PortalInvitation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    billingSetup($this);
    $this->account = PatientAccount::create(['patient_id' => $this->patient->id, 'email' => 'ana@example.com', 'password' => 'clave12345', 'is_active' => true])->fresh();
});

function portalLogin(object $test, string $password = 'clave12345'): TestResponse
{
    return $test->post(route('portal.login.store'), ['email' => 'ANA@example.com', 'password' => $password]);
}

it('el paciente ingresa con su guard propio, separado del personal', function () {
    portalLogin($this)->assertRedirect(route('portal.home'));

    $this->assertAuthenticatedAs($this->account, 'patient');
    $this->assertGuest('web');
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    expect($this->account->fresh()->last_login_at)->not->toBeNull();
});

it('rechaza credenciales incorrectas y cuentas desactivadas', function () {
    portalLogin($this, 'incorrecta')->assertSessionHasErrors('email');
    $this->assertGuest('patient');

    $this->account->update(['is_active' => false]);
    portalLogin($this)->assertSessionHasErrors('email');
    $this->assertGuest('patient');
});

it('una cuenta desactivada con sesión abierta queda afuera', function () {
    $this->actingAs($this->account, 'patient');
    $this->account->update(['is_active' => false]);

    $this->get(route('portal.home'))->assertRedirect(route('portal.login'));
    $this->assertGuest('patient');
});

it('el personal no entra al portal ni el paciente al sistema', function () {
    $this->actingAs($this->dentist)->get(route('portal.home'))->assertRedirect(route('portal.login'));

    // Sesión real del portal (sin actingAs, que cambia el guard por defecto del test).
    auth()->guard('web')->logout();
    auth()->shouldUse('web');
    portalLogin($this)->assertRedirect(route('portal.home'));
    $this->get(route('patients.index'))->assertRedirect(route('login'));
    $this->get(route('portal.home'))->assertOk();
});

it('muestra solo los datos propios y sin notas clínicas internas', function () {
    $other = Patient::factory()->create();
    $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), [
        'consulted_at' => '2026-12-01T09:30', 'reason' => 'Control', 'subjective' => 'NOTA-SUBJETIVA-SECRETA',
        'objective' => 'x', 'assessment' => 'DIAGNOSTICO-INTERNO', 'plan' => 'y', 'anesthesia_used' => '0', 'allergies_acknowledged' => '0', 'procedures' => [],
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->account, 'patient');
    $response = $this->get(route('portal.history'))->assertOk();
    $response->assertInertia(fn ($page) => $page->component('portal/history')->has('consultations', 1)->where('consultations.0.reason', 'Control'));
    expect($response->getContent())->not->toContain('NOTA-SUBJETIVA-SECRETA')->not->toContain('DIAGNOSTICO-INTERNO');

    $this->get(route('portal.home'))->assertInertia(fn ($page) => $page->component('portal/home')->has('balance'));
    $this->get(route('portal.odontogram'))->assertInertia(fn ($page) => $page->component('portal/odontogram'));
    expect($other->id)->not->toBe($this->patient->id);
});

it('descarga sus recibos y recetas, pero los de otro paciente dan 404', function () {
    $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this))->assertSessionHasNoErrors();
    $other = Patient::factory()->create();
    $this->actingAs($this->reception)->post(route('patients.payments.store', $other), paymentPayload($this, ['treatment_plan_id' => '']))->assertSessionHasNoErrors();
    $this->actingAs($this->dentist)->post(route('patients.prescriptions.store', $other), ['type' => 'indicaciones', 'instructions' => 'Reposo'])->assertSessionHasNoErrors();

    [$mine, $theirs] = Payment::orderBy('id')->get()->all();
    $this->actingAs($this->account, 'patient');

    $this->get(route('portal.documents'))->assertInertia(fn ($page) => $page->has('receipts', 1)->has('prescriptions', 0));
    $this->get(route('portal.receipts.pdf', $mine))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('portal.receipts.pdf', $theirs))->assertNotFound();
    $this->get(route('portal.prescriptions.pdf', Prescription::firstOrFail()))->assertNotFound();
});

it('recepción invita al paciente y este crea su contraseña con el enlace', function () {
    Notification::fake();
    $this->account->delete();
    $patient = Patient::factory()->create();

    $this->actingAs($this->reception)->post(route('patients.portal.invite', $patient), ['email' => 'Nuevo@Example.com'])->assertSessionHasNoErrors();

    $account = PatientAccount::where('patient_id', $patient->id)->firstOrFail();
    expect($account->email)->toBe('nuevo@example.com')->and($account->invited_at)->not->toBeNull();

    $token = null;
    Notification::assertSentTo($account, PortalInvitation::class, function (PortalInvitation $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->post(route('portal.password.update'), ['token' => $token, 'email' => 'nuevo@example.com', 'password' => 'corta', 'password_confirmation' => 'corta'])
        ->assertSessionHasErrors('password');
    $this->post(route('portal.password.update'), ['token' => $token, 'email' => 'nuevo@example.com', 'password' => 'MiClave2026', 'password_confirmation' => 'MiClave2026'])
        ->assertSessionHasNoErrors();

    $this->post(route('portal.login.store'), ['email' => 'nuevo@example.com', 'password' => 'MiClave2026'])->assertRedirect(route('portal.home'));
});

it('no reutiliza el correo de otro paciente y solo quien edita pacientes gestiona el acceso', function () {
    $other = Patient::factory()->create();

    $this->actingAs($this->reception)->post(route('patients.portal.invite', $other), ['email' => 'ana@example.com'])->assertSessionHasErrors('email');

    $this->actingAs($this->reception)->post(route('patients.portal.toggle', $this->patient))->assertRedirect();
    expect($this->account->fresh()->is_active)->toBeFalse();
});

it('el pedido de enlace no revela si el correo existe', function () {
    Notification::fake();

    $existing = $this->post(route('portal.password.email'), ['email' => 'ana@example.com'])->assertRedirect();
    $missing = $this->post(route('portal.password.email'), ['email' => 'nadie@example.com'])->assertRedirect();

    expect(session('status'))->not->toBeNull();
    Notification::assertSentTo($this->account, PortalInvitation::class);
    expect(Password::broker('patients'))->not->toBeNull()
        ->and($existing->getStatusCode())->toBe($missing->getStatusCode());
});
