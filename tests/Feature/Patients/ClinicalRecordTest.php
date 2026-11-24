<?php

use App\Enums\AllergyType;
use App\Enums\ConditionType;
use App\Enums\Severity;
use App\Exceptions\ImmutableRecordException;
use App\Models\Allergy;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PatientCondition;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function addAllergy(Patient $patient, User $by, array $overrides = []): Allergy
{
    return $patient->allergies()->create(array_merge([
        'type' => AllergyType::Drug,
        'allergen' => 'Penicilina',
        'severity' => Severity::Severe,
        'reaction' => 'Edema',
        'recorded_by' => $by->id,
    ], $overrides));
}

describe('antecedentes versionados', function () {
    it('guarda cada cambio como versión nueva sin tocar la anterior', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();

        $this->actingAs($dentist)->post(route('patients.history.store', $patient), [
            'medical_background' => 'Hipotiroidismo',
            'current_medication' => 'Levotiroxina 50 mcg',
        ])->assertRedirect(route('patients.clinical', $patient));

        $this->actingAs($dentist)->post(route('patients.history.store', $patient), [
            'medical_background' => 'Hipotiroidismo',
            'current_medication' => 'Levotiroxina 75 mcg',
            'change_reason' => 'Ajuste de dosis',
        ])->assertSessionHasNoErrors();

        $versions = MedicalHistory::where('patient_id', $patient->id)->orderBy('version')->get();
        expect($versions)->toHaveCount(2)
            ->and($versions[0]->current_medication)->toBe('Levotiroxina 50 mcg')
            ->and($versions[1]->current_medication)->toBe('Levotiroxina 75 mcg')
            ->and($versions[1]->version)->toBe(2)
            ->and($patient->currentMedicalHistory->version)->toBe(2);
    });

    it('exige el motivo al cambiar una versión existente', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $this->actingAs($dentist)->post(route('patients.history.store', $patient), ['medical_background' => 'Asma']);

        $this->actingAs($dentist)->post(route('patients.history.store', $patient), ['medical_background' => 'Asma leve'])
            ->assertSessionHasErrors(['change_reason' => 'El campo motivo del cambio es obligatorio.']);
    });

    it('la base de datos y el modelo impiden modificar o borrar versiones', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $this->actingAs($dentist)->post(route('patients.history.store', $patient), ['medical_background' => 'Asma']);
        $history = MedicalHistory::firstOrFail();

        expect(fn () => $history->update(['habits' => 'x']))->toThrow(ImmutableRecordException::class)
            ->and(fn () => $history->delete())->toThrow(ImmutableRecordException::class)
            ->and(fn () => DB::table('medical_histories')->update(['version' => 9]))->toThrow(QueryException::class)
            ->and(fn () => DB::table('medical_histories')->delete())->toThrow(QueryException::class);
    });

    it('guarda los datos clínicos cifrados en la base', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $this->actingAs($dentist)->post(route('patients.history.store', $patient), ['current_medication' => 'Warfarina 5 mg']);
        addAllergy($patient, $dentist, ['allergen' => 'Lidocaína']);

        $rawHistory = DB::table('medical_histories')->value('current_medication');
        $rawAllergy = DB::table('allergies')->value('allergen');

        expect($rawHistory)->not->toContain('Warfarina')
            ->and($rawAllergy)->not->toContain('Lidocaína')
            ->and(MedicalHistory::first()->current_medication)->toBe('Warfarina 5 mg');
    });

    it('no copia el texto clínico a la auditoría', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $this->actingAs($dentist)->post(route('patients.history.store', $patient), ['current_medication' => 'Warfarina 5 mg']);

        expect(DB::table('audit_logs')->pluck('new_values')->implode(' '))->not->toContain('Warfarina');
    });
});

describe('acceso a la ficha clínica', function () {
    it('recepción no ve ni edita la ficha clínica', function () {
        $patient = Patient::factory()->create();
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get(route('patients.clinical', $patient))->assertForbidden();
        $this->actingAs($reception)->post(route('patients.history.store', $patient), ['medical_background' => 'x'])->assertForbidden();
        $this->actingAs($reception)->post(route('patients.allergies.store', $patient), [
            'type' => 'medicamento', 'allergen' => 'Penicilina', 'severity' => 'grave',
        ])->assertForbidden();
    });

    it('recepción sí ve las alertas de alergia en la pantalla del paciente', function () {
        $patient = Patient::factory()->create();
        addAllergy($patient, User::factory()->dentist()->create());

        $this->actingAs(User::factory()->reception()->create())
            ->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('header.alerts.has_allergies', true)
                ->where('header.alerts.items.0.label', 'Alergia: Penicilina')
                ->where('header.alerts.items.0.level', 'critical'));
    });

    it('las alertas aparecen en todas las pantallas del paciente', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        addAllergy($patient, $dentist);
        $patient->conditions()->create(['condition' => ConditionType::Anticoagulants, 'recorded_by' => $dentist->id]);

        foreach (['patients.show', 'patients.clinical', 'patients.consents.index', 'patients.consents.create'] as $route) {
            $this->actingAs($dentist)->get(route($route, $patient))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has('header.alerts.items', 2));
        }
    });

    it('un usuario sin permiso de alertas no las recibe', function () {
        $patient = Patient::factory()->create();
        addAllergy($patient, User::factory()->dentist()->create());
        $user = User::factory()->create();
        $user->givePermissionTo('patients.view');

        $this->actingAs($user)->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page->where('header.alerts', null));
    });
});

describe('alergias y condiciones', function () {
    it('el odontólogo registra una alergia', function () {
        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->dentist()->create())
            ->post(route('patients.allergies.store', $patient), [
                'type' => 'anestesico', 'allergen' => 'Articaína', 'severity' => 'moderada', 'reaction' => 'Urticaria',
            ])->assertRedirect(route('patients.clinical', $patient));

        expect($patient->allergies()->active()->count())->toBe(1);
    });

    it('desactivar conserva el registro con quién, cuándo y por qué', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $allergy = addAllergy($patient, $dentist);

        $this->actingAs($dentist)
            ->post(route('patients.allergies.deactivate', [$patient, $allergy]), ['reason' => 'Descartada por alergólogo'])
            ->assertRedirect();

        $allergy->refresh();
        expect($allergy->is_active)->toBeFalse()
            ->and($allergy->deactivated_by)->toBe($dentist->id)
            ->and($allergy->deactivation_reason)->toBe('Descartada por alergólogo')
            ->and(fn () => $allergy->delete())->toThrow(ImmutableRecordException::class);

        $this->actingAs($dentist)->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page->has('header.alerts.items', 0));
    });

    it('exige el motivo para desactivar', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $allergy = addAllergy($patient, $dentist);

        $this->actingAs($dentist)
            ->post(route('patients.allergies.deactivate', [$patient, $allergy]), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    });

    it('no permite desactivar la alergia de otro paciente', function () {
        $dentist = User::factory()->dentist()->create();
        $allergy = addAllergy(Patient::factory()->create(), $dentist);
        $otherPatient = Patient::factory()->create();

        $this->actingAs($dentist)
            ->post(route('patients.allergies.deactivate', [$otherPatient, $allergy]), ['reason' => 'Intento indebido'])
            ->assertNotFound();
    });

    it('no duplica una condición activa', function () {
        $dentist = User::factory()->dentist()->create();
        $patient = Patient::factory()->create();
        $patient->conditions()->create(['condition' => ConditionType::Diabetes, 'recorded_by' => $dentist->id]);

        $this->actingAs($dentist)
            ->post(route('patients.conditions.store', $patient), ['condition' => 'diabetes'])
            ->assertSessionHasErrors(['condition' => 'Esa condición ya está registrada y activa.']);

        expect(PatientCondition::count())->toBe(1);
    });

    it('no permite cambios clínicos en un paciente archivado', function () {
        $patient = Patient::factory()->create();
        $patient->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('patients.allergies.store', $patient), ['type' => 'latex', 'allergen' => 'Látex', 'severity' => 'leve'])
            ->assertNotFound();
    });
});
