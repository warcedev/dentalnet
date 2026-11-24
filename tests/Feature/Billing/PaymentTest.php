<?php

require_once __DIR__.'/BillingHelpers.php';

use App\Actions\Billing\RegisterPayment;
use App\Exceptions\ImmutableRecordException;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Support\SpanishNumber;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => billingSetup($this));

it('recepción registra pagos con número de recibo correlativo', function () {
    $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this))->assertSessionHasNoErrors();
    $this->actingAs($this->reception)->post(route('patients.payments.store', Patient::factory()->create()), paymentPayload($this, ['treatment_plan_id' => '']))->assertSessionHasNoErrors();

    expect(Payment::orderBy('id')->pluck('receipt_number')->all())->toBe([1, 2])
        ->and(Payment::first()->amount)->toBe(300000)
        ->and(Payment::first()->received_by)->toBe($this->reception->id);
});

it('valida monto, fecha y número de operación', function (array $payload, string $field) {
    $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this, $payload))
        ->assertSessionHasErrors($field);
})->with([
    'monto cero' => [['amount' => '0'], 'amount'],
    'fecha futura' => [['paid_on' => '2026-12-02'], 'paid_on'],
    'transferencia sin operación' => [['method' => 'transferencia'], 'reference'],
]);

it('no acepta un plan de otro paciente', function () {
    $otherPlan = TreatmentPlan::create(['patient_id' => Patient::factory()->create()->id, 'title' => 'X', 'professional_id' => $this->dentist->id]);

    $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this, ['treatment_plan_id' => $otherPlan->id]))
        ->assertSessionHasErrors('treatment_plan_id');
});

it('el odontólogo no registra pagos', function () {
    $this->actingAs($this->dentist)->post(route('patients.payments.store', $this->patient), paymentPayload($this))->assertForbidden();
});

describe('anulación', function () {
    beforeEach(function () {
        $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this));
        $this->payment = Payment::firstOrFail();
    });

    it('solo el administrador anula, con motivo, y el pago no se borra', function () {
        $this->actingAs($this->reception)->post(route('patients.payments.void', [$this->patient, $this->payment]), ['reason' => 'Error de carga'])->assertForbidden();

        $this->actingAs($this->admin)->post(route('patients.payments.void', [$this->patient, $this->payment]), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('patients.payments.void', [$this->patient, $this->payment]), ['reason' => 'Error de carga'])->assertSessionHasNoErrors();

        expect($this->payment->fresh()->voided_by)->toBe($this->admin->id)->and(Payment::count())->toBe(1);
    });

    it('un pago no se modifica ni se borra, y se anula una sola vez', function () {
        expect(fn () => $this->payment->update(['amount' => 1]))->toThrow(ImmutableRecordException::class)
            ->and(fn () => $this->payment->delete())->toThrow(ImmutableRecordException::class);

        // Instancia limpia: la de arriba quedó con el intento de cambio pendiente (y rechazado).
        $payment = $this->payment->fresh();
        app(RegisterPayment::class)->void($payment, $this->admin, 'Error de carga');
        expect(fn () => $payment->forceFill(['void_reason' => 'otro'])->save())->toThrow(ImmutableRecordException::class)
            ->and(fn () => app(RegisterPayment::class)->void($this->payment->fresh(), $this->admin, 'Otra vez'))
            ->toThrow(ValidationException::class);
    });
});

describe('recibo PDF', function () {
    beforeEach(function () {
        $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this, ['amount' => '1.200.000']));
        $this->payment = Payment::firstOrFail();
    });

    it('genera el PDF con el monto en letras', function () {
        $response = $this->actingAs($this->reception)->get(route('patients.payments.receipt', [$this->patient, $this->payment]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        expect($response->getContent())->toStartWith('%PDF');

        $html = view('pdf.receipt', [
            'payment' => $this->payment->load(['receiver', 'plan']), 'patient' => $this->patient,
            'amountInWords' => SpanishNumber::toWords(1200000), 'balance' => 0,
            'clinic' => ['name' => 'DentalWeb', 'address' => '', 'phone' => ''],
        ])->render();
        expect($html)->toContain('Gs. 1.200.000')->toContain('guaraníes un millón doscientos mil')->toContain('N.º 0000001');
    });

    it('no se puede pedir el recibo de un pago a través de otro paciente', function () {
        $this->actingAs($this->reception)->get(route('patients.payments.receipt', [Patient::factory()->create(), $this->payment]))->assertNotFound();
    });
});

it('el recibo no queda en la caché del navegador', function () {
    $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this));

    $response = $this->actingAs($this->reception)->get(route('patients.payments.receipt', [$this->patient, Payment::firstOrFail()]));

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Disposition'))->toContain('inline');
});
