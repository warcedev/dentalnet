<?php

require_once __DIR__.'/BillingHelpers.php';

use App\Actions\Billing\RegisterPayment;
use App\Actions\Billing\ScheduleInstallments;
use App\Actions\Treatments\ManageTreatmentItems;
use App\Enums\AdjustmentType;
use App\Models\Payment;
use App\Services\Billing\BalanceCalculator;
use App\Services\Billing\InstallmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => billingSetup($this));

describe('regla 4: el saldo se calcula, nunca se guarda', function () {
    it('no existe ninguna columna de saldo en la base', function () {
        foreach (['patients', 'treatment_plans', 'treatment_items', 'payments', 'installments'] as $table) {
            expect(collect(Schema::getColumnListing($table))->filter(fn ($c) => str_contains($c, 'balance') || str_contains($c, 'saldo')))->toBeEmpty();
        }
    });

    it('suma solo aceptados, en curso y completados, y resta los pagos válidos', function () {
        item($this, 250000, 'aceptado');
        item($this, 900000, 'en_curso');
        item($this, 1000000, 'completado');
        item($this, 5000000, 'propuesto');
        item($this, 777000, 'cancelado');

        $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this));
        $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this, ['amount' => '100.000']));
        app(RegisterPayment::class)->void(Payment::latest('id')->firstOrFail(), $this->admin, 'Error');

        expect(app(BalanceCalculator::class)->forPatient($this->patient))->toBe([
            'proposed' => 5000000,
            'billed' => 2150000,
            'paid' => 300000,
            'balance' => 1850000,
        ]);
    });

    it('un ajuste de precio sobre un completado cambia el saldo sin tocar el ítem', function () {
        $done = item($this, 1000000, 'completado');
        app(ManageTreatmentItems::class)->adjust($done, $this->admin, AdjustmentType::Price, '800000', 'Descuento');

        expect($done->fresh()->price)->toBe(1000000)
            ->and($done->fresh()->effectivePrice())->toBe(800000)
            ->and(app(BalanceCalculator::class)->forPatient($this->patient)['balance'])->toBe(800000);
    });

    it('un pago mayor al total deja saldo a favor', function () {
        item($this, 100000);
        $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this, ['amount' => '150.000']));

        expect(app(BalanceCalculator::class)->forPatient($this->patient)['balance'])->toBe(-50000);
    });

    it('la pantalla muestra el mismo saldo calculado', function () {
        item($this, 250000);

        $this->actingAs($this->reception)->get(route('patients.treatments', $this->patient))
            ->assertInertia(fn (Assert $page) => $page->where('summary.balance', 250000)->where('plans.0.summary.billed', 250000));
    });
});

describe('cuotas', function () {
    it('divide el saldo en cuotas enteras y la diferencia va en la última', function () {
        item($this, 1000000);

        app(ScheduleInstallments::class)->handle($this->plan, 3, CarbonImmutable::parse('2026-12-10'));

        $installments = $this->plan->installments()->get();
        expect($installments->pluck('amount')->all())->toBe([333333, 333333, 333334])
            ->and($installments->pluck('due_date')->map->toDateString()->all())->toBe(['2026-12-10', '2027-01-10', '2027-02-10']);
    });

    it('calcula el estado de cada cuota imputando los pagos en orden', function () {
        item($this, 900000);
        app(ScheduleInstallments::class)->handle($this->plan, 3, CarbonImmutable::parse('2026-11-15'));
        $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this, ['amount' => '400.000']));

        $rows = app(InstallmentStatus::class)->for($this->plan);

        expect(array_column($rows, 'status'))->toBe(['pagada', 'parcial', 'pendiente'])
            ->and($rows[1]['paid'])->toBe(100000)
            ->and($rows[1]['pending'])->toBe(200000);

        $this->travelTo('2027-01-01');
        expect(array_column(app(InstallmentStatus::class)->for($this->plan), 'status'))->toBe(['pagada', 'vencida', 'pendiente']);
    });

    it('no financia un plan sin saldo', function () {
        expect(fn () => app(ScheduleInstallments::class)->handle($this->plan, 3, CarbonImmutable::parse('2026-12-10')))
            ->toThrow(ValidationException::class);
    });
});
