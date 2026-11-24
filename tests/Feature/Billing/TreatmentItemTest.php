<?php

require_once __DIR__.'/BillingHelpers.php';

use App\Exceptions\ImmutableRecordException;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\TreatmentAdjustment;
use App\Models\TreatmentItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => billingSetup($this));

function itemRoute(object $test, string $name, TreatmentItem $item): string
{
    return route($name, ['patient' => $test->patient, 'treatmentPlan' => $test->plan, 'treatmentItem' => $item]);
}

it('el odontólogo agrega un tratamiento con precio en guaraníes enteros', function () {
    $this->actingAs($this->dentist)->post(route('patients.treatments.items.store', [$this->patient, $this->plan]), [
        'procedure_id' => Procedure::where('name', 'Corona de porcelana')->value('id'),
        'price' => 'Gs. 2.200.000',
        'tooth_code' => 16,
    ])->assertSessionHasNoErrors();

    $item = TreatmentItem::firstOrFail();
    expect($item->price)->toBe(2200000)->and($item->status->value)->toBe('propuesto')->and($item->warranty_months)->toBe(36);
});

it('recepción no gestiona tratamientos pero sí los ve', function () {
    $this->actingAs($this->reception)->post(route('patients.treatments.items.store', [$this->patient, $this->plan]), [
        'procedure_id' => Procedure::value('id'), 'price' => 1000,
    ])->assertForbidden();

    $this->actingAs($this->reception)->get(route('patients.treatments', $this->patient))->assertOk();
});

it('sigue el flujo propuesto → aceptado → en curso → completado', function () {
    $item = item($this, 250000, 'propuesto');

    foreach (['aceptado', 'en_curso', 'completado'] as $status) {
        $this->actingAs($this->dentist)->post(itemRoute($this, 'patients.treatments.items.status', $item), ['status' => $status])->assertSessionHasNoErrors();
    }

    $item->refresh();
    expect($item->status->value)->toBe('completado')
        ->and($item->completed_on->toDateString())->toBe('2026-12-01')
        ->and($item->warrantyUntil()?->toDateString())->toBe('2027-12-01');
});

it('exige motivo para cancelar', function () {
    $item = item($this, 250000, 'propuesto');

    $this->actingAs($this->dentist)->post(itemRoute($this, 'patients.treatments.items.status', $item), ['status' => 'cancelado'])
        ->assertSessionHasErrors(['reason' => 'Indique el motivo de la cancelación.']);
});

describe('regla 3: un completado no se edita', function () {
    beforeEach(fn () => $this->done = item($this, 1000000, 'completado'));

    it('rechaza editarlo desde la pantalla', function () {
        $this->actingAs($this->dentist)->put(itemRoute($this, 'patients.treatments.items.update', $this->done), ['price' => 1])
            ->assertSessionHasErrors(['price' => 'Un tratamiento completado no se edita: registre un ajuste.']);
    });

    it('el modelo impide modificarlo o borrarlo', function () {
        expect(fn () => $this->done->update(['price' => 1]))->toThrow(ImmutableRecordException::class)
            ->and(fn () => $this->done->delete())->toThrow(ImmutableRecordException::class);
    });

    it('no admite cambios de estado', function () {
        $this->actingAs($this->dentist)->post(itemRoute($this, 'patients.treatments.items.status', $this->done), ['status' => 'cancelado', 'reason' => 'x'])
            ->assertSessionHasErrors('status');
    });

    it('se corrige con un ajuste auditado e inmutable', function () {
        $this->actingAs($this->dentist)->post(itemRoute($this, 'patients.treatments.items.adjust', $this->done), [
            'type' => 'garantia', 'new_value' => '24', 'reason' => 'Garantía extendida por convenio',
        ])->assertSessionHasNoErrors();

        $adjustment = TreatmentAdjustment::firstOrFail();
        expect($adjustment->old_value)->toBe('12')
            ->and($adjustment->new_value)->toBe('24')
            ->and($adjustment->created_by)->toBe($this->dentist->id)
            ->and($this->done->fresh()->effectiveWarrantyMonths())->toBe(24)
            ->and($this->done->fresh()->warranty_months)->toBe(12)
            ->and(fn () => $adjustment->delete())->toThrow(ImmutableRecordException::class)
            ->and(fn () => DB::table('treatment_adjustments')->update(['new_value' => '99']))->toThrow(QueryException::class);

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => TreatmentAdjustment::class, 'event' => 'created']);
    });

    it('exige el motivo del ajuste', function () {
        $this->actingAs($this->dentist)->post(itemRoute($this, 'patients.treatments.items.adjust', $this->done), ['type' => 'precio', 'new_value' => '900000'])
            ->assertSessionHasErrors('reason');
    });

    it('no admite ajustes sobre un tratamiento que todavía se puede editar', function () {
        $open = item($this, 100000, 'aceptado');

        $this->actingAs($this->dentist)->post(itemRoute($this, 'patients.treatments.items.adjust', $open), [
            'type' => 'precio', 'new_value' => '50000', 'reason' => 'Descuento',
        ])->assertSessionHasErrors('type');
    });
});

it('no se puede acceder a un ítem de otro paciente por la URL', function () {
    $other = Patient::factory()->create();
    $item = item($this, 1000);

    $this->actingAs($this->dentist)->post(route('patients.treatments.items.status', ['patient' => $other, 'treatmentPlan' => $this->plan, 'treatmentItem' => $item]), ['status' => 'en_curso'])
        ->assertNotFound();
});
