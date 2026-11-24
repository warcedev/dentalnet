<?php

require_once __DIR__.'/../Billing/BillingHelpers.php';

use App\Enums\AllergyType;
use App\Enums\Severity;
use App\Exceptions\ImmutableRecordException;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ToothEvent;
use App\Models\User;
use App\Services\Odontogram\OdontogramInsights;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => billingSetup($this));

/** @return array<string, mixed> */
function consultationPayload(array $overrides = []): array
{
    return array_merge([
        'consulted_at' => '2026-12-01T09:30',
        'reason' => 'Dolor en molar',
        'subjective' => 'Dolor al frío desde hace una semana',
        'objective' => 'Caries profunda en 36',
        'assessment' => 'Pulpitis reversible',
        'plan' => 'Obturación',
        'anesthesia_used' => '0',
        'allergies_acknowledged' => '0',
        'procedures' => [],
    ], $overrides);
}

function procedureId(string $name): int
{
    return (int) Procedure::where('name', $name)->value('id');
}

it('registra la consulta con las notas SOAP cifradas', function () {
    $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload())->assertSessionHasNoErrors();

    $consultation = Consultation::firstOrFail();
    expect($consultation->assessment)->toBe('Pulpitis reversible')
        ->and($consultation->professional_id)->toBe($this->dentist->id)
        ->and(DB::table('consultations')->value('assessment'))->not->toContain('Pulpitis')
        ->and(DB::table('consultations')->value('reason'))->not->toContain('Dolor')
        ->and(DB::table('audit_logs')->pluck('new_values')->implode(' '))->not->toContain('Pulpitis');
});

it('recepción no ve ni registra consultas', function () {
    $this->actingAs($this->reception)->get(route('patients.consultations', $this->patient))->assertForbidden();
    $this->actingAs($this->reception)->post(route('patients.consultations.store', $this->patient), consultationPayload())->assertForbidden();
});

describe('regla 1: la consulta no se sobrescribe', function () {
    beforeEach(function () {
        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload());
        $this->consultation = Consultation::firstOrFail();
    });

    it('es inmutable en el modelo y en la base', function () {
        expect(fn () => $this->consultation->update(['plan' => 'x']))->toThrow(ImmutableRecordException::class)
            ->and(fn () => DB::table('consultations')->update(['next_visit' => 'x']))->toThrow(QueryException::class);
    });

    it('solo el profesional que la registró agrega adendas', function () {
        $other = User::factory()->dentist()->create();

        $this->actingAs($other)->post(route('patients.consultations.addenda.store', [$this->patient, $this->consultation]), ['body' => 'Aclaración'])->assertForbidden();
        $this->actingAs($this->dentist)->post(route('patients.consultations.addenda.store', [$this->patient, $this->consultation]), ['body' => 'Se indicó analgésico'])->assertSessionHasNoErrors();

        expect($this->consultation->addenda()->first()->body)->toBe('Se indicó analgésico');
    });
});

describe('regla 6: alergias antes de la anestesia', function () {
    beforeEach(function () {
        $this->patient->allergies()->create(['type' => AllergyType::Anesthetic, 'allergen' => 'Lidocaína', 'severity' => Severity::Severe, 'recorded_by' => $this->dentist->id]);
    });

    it('exige confirmar las alergias si se aplicó anestesia', function () {
        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload(['anesthesia_used' => '1']))
            ->assertSessionHasErrors(['allergies_acknowledged' => 'El paciente tiene alergias registradas: confirme que las revisó antes de aplicar anestesia.']);

        expect(Consultation::count())->toBe(0);
    });

    it('también la exige si un procedimiento requiere anestesia aunque no se haya marcado', function () {
        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload([
            'procedures' => [['procedure_id' => procedureId('Extracción simple'), 'tooth_code' => 18]],
        ]))->assertSessionHasErrors('allergies_acknowledged');
    });

    it('con la confirmación registra quién y cuándo', function () {
        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload([
            'anesthesia_used' => '1', 'allergies_acknowledged' => '1',
        ]))->assertSessionHasNoErrors();

        expect(Consultation::firstOrFail()->allergies_acknowledged_at)->not->toBeNull();
    });

    it('la pantalla informa las alergias para mostrar la alerta', function () {
        $this->actingAs($this->dentist)->get(route('patients.consultations', $this->patient))
            ->assertInertia(fn ($page) => $page->where('allergies', ['Lidocaína (Grave)']));
    });
});

describe('integración con el plan y el odontograma', function () {
    it('completa el tratamiento vinculado y lo marca en el odontograma', function () {
        $item = item($this, 250000, 'aceptado', ['tooth_code' => 36, 'surface' => 'oclusal']);

        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload([
            'anesthesia_used' => '1',
            'procedures' => [['treatment_item_id' => $item->id]],
        ]))->assertSessionHasNoErrors();

        $consultation = Consultation::firstOrFail();
        expect($item->fresh()->status->value)->toBe('completado')
            ->and($item->fresh()->completed_on->toDateString())->toBe('2026-12-01')
            ->and($item->fresh()->consultation_id)->toBe($consultation->id);

        $event = ToothEvent::firstOrFail();
        expect($event->tooth_code)->toBe(36)
            ->and($event->surface->value)->toBe('oclusal')
            ->and($event->condition->value)->toBe('restauracion')
            ->and($event->status->value)->toBe('tratado')
            ->and($event->consultation_id)->toBe($consultation->id);
    });

    it('una limpieza en consulta actualiza la fecha de la última limpieza', function () {
        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload([
            'procedures' => [['procedure_id' => procedureId('Limpieza (profilaxis)')]],
        ]))->assertSessionHasNoErrors();

        expect(app(OdontogramInsights::class)->lastCleaning($this->patient->toothEvents()->get())?->toDateString())->toBe('2026-12-01');
    });

    it('pide el diente y la superficie cuando el procedimiento los necesita', function () {
        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload([
            'anesthesia_used' => '1',
            'procedures' => [['procedure_id' => procedureId('Obturación con resina'), 'tooth_code' => 36]],
        ]))->assertSessionHasErrors(['procedures.0.surface' => 'Indique la superficie para «Obturación con resina».']);

        expect(Consultation::count())->toBe(0)->and(ToothEvent::count())->toBe(0);
    });

    it('no completa un tratamiento que no fue aceptado', function () {
        $item = item($this, 250000, 'propuesto');

        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $this->patient), consultationPayload([
            'procedures' => [['treatment_item_id' => $item->id]],
        ]))->assertSessionHasErrors(['procedures.0.treatment_item_id' => 'El tratamiento debe estar aceptado o en curso para completarlo.']);
    });

    it('no completa tratamientos de otro paciente', function () {
        $other = Patient::factory()->create();
        $item = item($this, 250000, 'aceptado');

        $this->actingAs($this->dentist)->post(route('patients.consultations.store', $other), consultationPayload([
            'procedures' => [['treatment_item_id' => $item->id]],
        ]))->assertSessionHasErrors('procedures.0.treatment_item_id');

        expect($item->fresh()->status->value)->toBe('aceptado');
    });
});
