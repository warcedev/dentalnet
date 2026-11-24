<?php

use App\Actions\Consents\PublishConsentVersion;
use App\Exceptions\ImmutableRecordException;
use App\Models\Consent;
use App\Models\ConsentSignature;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DemoSignature;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->dentist = User::factory()->dentist()->create(['name' => 'Dra. Prueba']);
    $this->consent = app(PublishConsentVersion::class)->handle(
        $this->dentist,
        'Consentimiento para extracción',
        'Yo, {paciente} ({documento}), autorizo la extracción indicada por {profesional} el {fecha}. Texto suficientemente largo para la validación.'
    );
});

function signPayload(Consent $consent, array $overrides = []): array
{
    return array_merge([
        'consent_id' => $consent->id,
        'signer_name' => 'Ana Núñez',
        'signer_document' => '7654321',
        'signer_relationship' => 'paciente',
        'accepted' => '1',
        'signature' => DemoSignature::dataUrl(),
    ], $overrides);
}

it('guarda la firma en almacenamiento privado con copia del texto y huellas', function () {
    $patient = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Núñez', 'document_number' => '7654321']);

    $this->actingAs($this->dentist)
        ->post(route('patients.consents.store', $patient), signPayload($this->consent))
        ->assertRedirect();

    $signature = ConsentSignature::firstOrFail();

    Storage::disk('local')->assertExists($signature->signature_path);
    expect($signature->signature_path)->toStartWith('consentimientos/')
        ->and($signature->body_snapshot)->toContain('Yo, Ana Núñez (Cédula de identidad 7654321)')
        ->and($signature->body_snapshot)->toContain('Dra. Prueba')
        ->and($signature->body_snapshot)->not->toContain('{paciente}')
        ->and($signature->body_hash)->toBe(hash('sha256', $signature->body_snapshot))
        ->and($signature->signature_hash)->toBe(hash('sha256', Storage::disk('local')->get($signature->signature_path)))
        ->and($signature->consent_version)->toBe(1)
        ->and($signature->witnessed_by)->toBe($this->dentist->id);
});

it('recepción también puede recoger consentimientos', function () {
    $patient = Patient::factory()->create();

    $this->actingAs(User::factory()->reception()->create())
        ->post(route('patients.consents.store', $patient), signPayload($this->consent))
        ->assertSessionHasNoErrors();

    expect(ConsentSignature::count())->toBe(1);
});

it('rechaza una firma vacía o que no es una imagen PNG', function (string $signature, string $message) {
    $patient = Patient::factory()->create();

    $this->actingAs($this->dentist)
        ->post(route('patients.consents.store', $patient), signPayload($this->consent, ['signature' => $signature]))
        ->assertSessionHasErrors(['signature' => $message]);

    expect(ConsentSignature::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'vacía' => [fn () => DemoSignature::blank(), 'La firma está vacía. Pida que firme en el recuadro.'],
    'no es PNG' => ['data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=', 'La firma no tiene un formato válido.'],
    'base64 inválido' => ['data:image/png;base64,@@@', 'La firma no tiene un formato válido.'],
]);

it('exige confirmar la lectura', function () {
    $this->actingAs($this->dentist)
        ->post(route('patients.consents.store', Patient::factory()->create()), signPayload($this->consent, ['accepted' => '0']))
        ->assertSessionHasErrors(['accepted' => 'Debe confirmar que el paciente leyó y comprendió el consentimiento.']);
});

it('un menor de edad no puede firmar por sí mismo', function () {
    $minor = Patient::factory()->minor()->create();

    $this->actingAs($this->dentist)
        ->post(route('patients.consents.store', $minor), signPayload($this->consent))
        ->assertSessionHasErrors(['signer_relationship' => 'El paciente es menor de edad: debe firmar el padre, la madre o el tutor legal.']);

    $this->actingAs($this->dentist)
        ->post(route('patients.consents.store', $minor), signPayload($this->consent, ['signer_relationship' => 'padre_madre']))
        ->assertSessionHasNoErrors();
});

it('una nueva versión de la plantilla no altera lo ya firmado', function () {
    $patient = Patient::factory()->create();
    $this->actingAs($this->dentist)->post(route('patients.consents.store', $patient), signPayload($this->consent));
    $original = ConsentSignature::firstOrFail()->body_snapshot;

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.consents.update', $this->consent), [
            'title' => 'Consentimiento para extracción',
            'body' => 'Texto nuevo y distinto para la versión dos de la plantilla, con {paciente} y suficiente largo.',
        ])->assertRedirect();

    $versions = Consent::where('code', $this->consent->code)->orderBy('version')->get();
    expect($versions)->toHaveCount(2)
        ->and($versions[0]->is_active)->toBeFalse()
        ->and($versions[1]->is_active)->toBeTrue()
        ->and($versions[1]->version)->toBe(2)
        ->and(ConsentSignature::firstOrFail()->body_snapshot)->toBe($original);

    // La versión vieja ya no se puede firmar.
    $this->actingAs($this->dentist)
        ->post(route('patients.consents.store', $patient), signPayload($versions[0]))
        ->assertSessionHasErrors('consent_id');
});

it('los consentimientos firmados son inmutables', function () {
    $patient = Patient::factory()->create();
    $this->actingAs($this->dentist)->post(route('patients.consents.store', $patient), signPayload($this->consent));
    $signature = ConsentSignature::firstOrFail();

    expect(fn () => $signature->update(['signer_name' => 'Otro']))->toThrow(ImmutableRecordException::class)
        ->and(fn () => $signature->delete())->toThrow(ImmutableRecordException::class)
        ->and(fn () => DB::table('consent_signatures')->update(['signer_name' => 'Otro']))->toThrow(QueryException::class);
});

describe('imagen de la firma', function () {
    beforeEach(function () {
        $this->patient = Patient::factory()->create();
        $this->actingAs($this->dentist)->post(route('patients.consents.store', $this->patient), signPayload($this->consent));
        $this->signature = ConsentSignature::firstOrFail();
    });

    it('se entrega solo por la ruta autorizada y sin caché', function () {
        $response = $this->actingAs($this->dentist)
            ->get(route('patients.consents.image', [$this->patient, $this->signature]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('rechaza a usuarios sin permiso e invitados', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('patients.consents.image', [$this->patient, $this->signature]))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('patients.consents.image', [$this->patient, $this->signature]))->assertRedirect(route('login'));
    });

    it('no se puede pedir a través de otro paciente', function () {
        $this->actingAs($this->dentist)
            ->get(route('patients.consents.image', [Patient::factory()->create(), $this->signature]))
            ->assertNotFound();
    });

    it('el archivo no está en la carpeta pública', function () {
        expect(file_exists(public_path('storage/'.$this->signature->signature_path)))->toBeFalse()
            ->and(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
            ->and(config('filesystems.disks.local.serve'))->toBeFalse();
    });
});

describe('plantillas', function () {
    it('solo el administrador las gestiona', function (string $role) {
        $this->actingAs(User::factory()->{$role}()->create())->get(route('admin.consents.index'))->assertForbidden();
        $this->actingAs(User::factory()->{$role}()->create())
            ->post(route('admin.consents.store'), ['title' => 'X', 'body' => str_repeat('texto ', 20)])
            ->assertForbidden();
    })->with(['dentist', 'reception']);

    it('el administrador crea una plantilla', function () {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.consents.store'), ['title' => 'Blanqueamiento', 'body' => str_repeat('Texto del consentimiento. ', 5)])
            ->assertRedirect(route('admin.consents.index'));

        expect(Consent::where('code', 'blanqueamiento')->where('version', 1)->exists())->toBeTrue();
    });
});
