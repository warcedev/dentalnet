<?php

use App\Exceptions\ImmutableRecordException;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function patientPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'María José',
        'last_name' => 'Benítez Ortiz',
        'document_type' => 'ci',
        'document_number' => '4.567.890',
        'birth_date' => '1985-03-14',
        'sex' => 'f',
        'phone' => '0981 123 456',
        'email' => 'Maria@Ejemplo.com',
        'accepts_communications' => '1',
    ], $overrides);
}

describe('alta y edición', function () {
    it('permite a recepción registrar un paciente y normaliza los datos', function () {
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->post(route('patients.store'), patientPayload())->assertRedirect();

        $patient = Patient::firstOrFail();
        expect($patient->document_number)->toBe('4567890')
            ->and($patient->email)->toBe('maria@ejemplo.com')
            ->and($patient->search_name)->toBe('maria jose benitez ortiz')
            ->and($patient->search_phone)->toBe('0981123456')
            ->and($patient->created_by)->toBe($reception->id)
            ->and($patient->accepts_communications)->toBeTrue();

        expect(AuditLog::where('auditable_type', Patient::class)->where('event', 'created')->exists())->toBeTrue();
    });

    it('rechaza un documento repetido con mensaje en español', function () {
        Patient::factory()->create(['document_number' => '4567890']);

        $this->actingAs(User::factory()->reception()->create())
            ->post(route('patients.store'), patientPayload())
            ->assertSessionHasErrors(['document_number' => 'Ya existe un paciente con ese documento.']);
    });

    it('avisa cuando el documento pertenece a un paciente archivado', function () {
        Patient::factory()->create(['document_number' => '4567890'])->delete();

        $this->actingAs(User::factory()->reception()->create())
            ->post(route('patients.store'), patientPayload())
            ->assertSessionHasErrors(['document_number' => 'Ya existe un paciente archivado con ese documento. Pida al administrador que lo restaure.']);
    });

    it('exige el tutor cuando el paciente es menor de edad', function () {
        $this->actingAs(User::factory()->reception()->create())
            ->post(route('patients.store'), patientPayload(['birth_date' => now()->subYears(10)->toDateString()]))
            ->assertSessionHasErrors(['guardian_name', 'guardian_document', 'guardian_relationship']);
    });

    it('valida en español los campos obligatorios y la fecha futura', function () {
        $this->actingAs(User::factory()->reception()->create())
            ->post(route('patients.store'), patientPayload(['first_name' => '', 'birth_date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors([
                'first_name' => 'El campo nombres es obligatorio.',
                'birth_date' => 'La fecha de nacimiento no puede ser futura.',
            ]);
    });

    it('permite editar y registra los valores anteriores en la auditoría', function () {
        $patient = Patient::factory()->create(['phone' => '0981 000 000']);

        $this->actingAs(User::factory()->reception()->create())
            ->put(route('patients.update', $patient), patientPayload(['document_number' => $patient->document_number, 'phone' => '0991 222 333']))
            ->assertRedirect(route('patients.show', $patient));

        $log = AuditLog::where('auditable_id', $patient->id)->where('event', 'updated')->latest('id')->firstOrFail();
        expect($log->old_values['phone'])->toBe('0981 000 000')
            ->and($log->new_values['phone'])->toBe('0991 222 333');
    });

    it('no permite registrar pacientes a un usuario sin rol', function () {
        $this->actingAs(User::factory()->create())->post(route('patients.store'), patientPayload())->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('patients.index'))->assertForbidden();
    });
});

describe('archivo lógico', function () {
    it('solo el administrador puede archivar', function (string $role) {
        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->{$role}()->create())
            ->delete(route('patients.archive', $patient))
            ->assertForbidden();

        expect($patient->fresh()->trashed())->toBeFalse();
    })->with(['dentist', 'reception']);

    it('archiva sin borrar y oculta al paciente de las búsquedas', function () {
        $admin = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $this->actingAs($admin)->delete(route('patients.archive', $patient))->assertRedirect(route('patients.index'));

        expect(Patient::withTrashed()->find($patient->id))->not->toBeNull()
            ->and(Patient::find($patient->id))->toBeNull();

        $this->actingAs($admin)->get(route('patients.index'))
            ->assertInertia(fn (Assert $page) => $page->has('patients.data', 0));
        $this->actingAs($admin)->get(route('patients.index', ['archivados' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('patients.data', 1));
    });

    it('impide ver o editar un paciente archivado a quien no puede restaurarlo', function () {
        $patient = Patient::factory()->create();
        $patient->delete();
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get(route('patients.show', $patient))->assertForbidden();
        $this->actingAs($reception)->get(route('patients.edit', $patient))->assertNotFound();
    });

    it('el administrador puede restaurar', function () {
        $patient = Patient::factory()->create();
        $patient->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('patients.restore', $patient))
            ->assertRedirect(route('patients.show', $patient));

        expect($patient->fresh()->trashed())->toBeFalse();
    });

    it('nunca permite el borrado físico', function () {
        $patient = Patient::factory()->create();

        expect(fn () => $patient->forceDelete())->toThrow(ImmutableRecordException::class);
        expect(Patient::withTrashed()->find($patient->id))->not->toBeNull();
    });
});

describe('ficha del paciente', function () {
    it('calcula edad y minoría de edad', function () {
        $minor = Patient::factory()->minor()->create(['birth_date' => now()->subYears(12)->subDay()->toDateString()]);
        $adult = Patient::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);

        expect($minor->age)->toBe(12)->and($minor->is_minor)->toBeTrue()
            ->and($adult->age)->toBe(18)->and($adult->is_minor)->toBeFalse();
    });

    it('muestra los datos y los permisos de la pantalla según el rol', function () {
        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->reception()->create())
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('patients/show')
                ->where('header.full_name', $patient->full_name)
                ->where('header.can.update', true)
                ->where('header.can.archive', false)
                ->where('header.can.view_clinical', false));
    });
});
