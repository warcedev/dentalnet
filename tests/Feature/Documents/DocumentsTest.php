<?php

require_once __DIR__.'/../Billing/BillingHelpers.php';

use App\Enums\AllergyType;
use App\Enums\Severity;
use App\Exceptions\ImmutableRecordException;
use App\Models\Document;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    billingSetup($this);
    Storage::fake('local');
});

function upload(object $test, UploadedFile $file, array $extra = []): TestResponse
{
    return $test->actingAs($test->dentist)->post(route('patients.documents.store', $test->patient), array_merge([
        'file' => $file,
        'category' => 'radiografia',
        'title' => 'Periapical 36',
        'description' => 'Lesión periapical',
        'tooth_code' => 36,
        'taken_on' => '2026-11-30',
    ], $extra));
}

describe('documentos', function () {
    it('guarda la imagen en disco privado con nombre aleatorio y la sirve solo a personal autorizado', function () {
        upload($this, UploadedFile::fake()->image('radiografía de Ana.png', 300, 200))->assertSessionHasNoErrors();

        $document = Document::firstOrFail();
        expect($document->path)->toStartWith("documentos/{$this->patient->id}/")
            ->and($document->path)->not->toContain('Ana')
            ->and($document->mime)->toBe('image/png')
            ->and(strlen($document->sha256))->toBe(64)
            ->and(DB::table('documents')->value('description'))->not->toContain('Lesión');
        Storage::disk('local')->assertExists($document->path);

        $this->actingAs($this->dentist)->get(route('patients.documents.file', [$this->patient, $document]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/png');

        // Recepción ve la pestaña (laboratorio) pero no los archivos clínicos.
        $this->actingAs($this->reception)->get(route('patients.documents.file', [$this->patient, $document]))->assertForbidden();
        $this->actingAs($this->reception)->get(route('patients.documents', $this->patient))
            ->assertInertia(fn ($page) => $page->where('documents', null));
    });

    it('rechaza tipos no permitidos, archivos disfrazados y tamaños excesivos', function () {
        upload($this, UploadedFile::fake()->create('script.php', 10, 'application/x-php'))->assertSessionHasErrors('file');
        upload($this, UploadedFile::fake()->createWithContent('foto.png', '<?php echo 1;'))->assertSessionHasErrors('file');
        config(['clinic.documents.max_kb' => 50]);
        upload($this, UploadedFile::fake()->image('grande.png')->size(60))->assertSessionHasErrors('file');

        expect(Document::count())->toBe(0);
    });

    it('no permite ver documentos de otro paciente por la URL', function () {
        upload($this, UploadedFile::fake()->image('rx.png'));
        $other = Patient::factory()->create();

        $this->actingAs($this->dentist)->get(route('patients.documents.file', [$other, Document::firstOrFail()]))->assertNotFound();
    });

    it('archivar no borra: el archivo y el registro se conservan', function () {
        upload($this, UploadedFile::fake()->image('rx.png'));
        $document = Document::firstOrFail();

        $this->actingAs($this->dentist)->delete(route('patients.documents.archive', [$this->patient, $document]))->assertRedirect();

        expect(Document::count())->toBe(0)->and(Document::withTrashed()->count())->toBe(1);
        Storage::disk('local')->assertExists($document->path);
        expect(fn () => Document::withTrashed()->firstOrFail()->forceDelete())->toThrow(ImmutableRecordException::class);
    });
});

describe('órdenes de laboratorio', function () {
    beforeEach(function () {
        $this->actingAs($this->dentist)->post(route('patients.lab-orders.store', $this->patient), [
            'laboratory' => 'Lab Central',
            'work_type' => 'corona',
            'teeth' => '36',
            'shade' => 'A2',
            'instructions' => 'Zirconio',
            'sent_on' => '2026-11-20',
            'due_on' => '2026-11-28',
        ])->assertSessionHasNoErrors();
        $this->order = LabOrder::firstOrFail();
    });

    it('detecta las órdenes vencidas y cifra las instrucciones', function () {
        expect($this->order->isOverdue())->toBeTrue()
            ->and(DB::table('lab_orders')->value('instructions'))->not->toContain('Zirconio');
    });

    it('sigue el circuito enviado → recibido → colocado y no permite saltos', function () {
        $this->actingAs($this->dentist)->post(route('patients.lab-orders.status', [$this->patient, $this->order]), ['status' => 'colocado'])->assertStatus(422);
        $this->actingAs($this->dentist)->post(route('patients.lab-orders.status', [$this->patient, $this->order]), ['status' => 'recibido'])->assertRedirect();
        $this->actingAs($this->dentist)->post(route('patients.lab-orders.status', [$this->patient, $this->order]), ['status' => 'colocado'])->assertRedirect();

        $order = $this->order->fresh();
        expect($order->status->value)->toBe('colocado')
            ->and($order->received_on?->toDateString())->toBe('2026-12-01')
            ->and($order->isOverdue())->toBeFalse();
    });
});

describe('recetas', function () {
    function prescriptionPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'receta',
            'medications' => [['drug' => 'Amoxicilina 500 mg', 'dose' => '1 cápsula', 'frequency' => 'cada 8 horas', 'duration' => '7 días']],
            'instructions' => 'Completar el tratamiento.',
            'allergies_acknowledged' => false,
        ], $overrides);
    }

    it('emite la receta cifrada, inmutable y con PDF', function () {
        $this->actingAs($this->dentist)->post(route('patients.prescriptions.store', $this->patient), prescriptionPayload())->assertSessionHasNoErrors();

        $prescription = Prescription::firstOrFail();
        expect($prescription->medications[0]['drug'])->toBe('Amoxicilina 500 mg')
            ->and(DB::table('prescriptions')->value('medications'))->not->toContain('Amoxicilina')
            ->and(DB::table('audit_logs')->pluck('new_values')->implode(' '))->not->toContain('Amoxicilina');

        $this->actingAs($this->dentist)->get(route('patients.prescriptions.pdf', [$this->patient, $prescription]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        expect(fn () => $prescription->update(['instructions' => 'otra']))->toThrow(ImmutableRecordException::class)
            ->and(fn () => DB::table('prescriptions')->update(['instructions' => 'x']))->toThrow(QueryException::class);
    });

    it('regla 6: con alergias exige confirmar antes de recetar', function () {
        $this->patient->allergies()->create(['type' => AllergyType::Drug, 'allergen' => 'Penicilina', 'severity' => Severity::Severe, 'recorded_by' => $this->dentist->id]);

        $this->actingAs($this->dentist)->post(route('patients.prescriptions.store', $this->patient), prescriptionPayload())
            ->assertSessionHasErrors('allergies_acknowledged');
        expect(Prescription::count())->toBe(0);

        $this->actingAs($this->dentist)->post(route('patients.prescriptions.store', $this->patient), prescriptionPayload(['allergies_acknowledged' => true]))
            ->assertSessionHasNoErrors();
        expect(Prescription::firstOrFail()->allergies_acknowledged_at)->not->toBeNull();
    });

    it('las indicaciones no requieren medicamentos y recepción no puede recetar', function () {
        $this->actingAs($this->dentist)->post(route('patients.prescriptions.store', $this->patient), [
            'type' => 'indicaciones', 'instructions' => 'No enjuagar por 24 horas.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->reception)->post(route('patients.prescriptions.store', $this->patient), prescriptionPayload())->assertForbidden();
    });
});
