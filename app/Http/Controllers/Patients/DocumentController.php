<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Documents\IssuePrescription;
use App\Actions\Documents\StoreDocument;
use App\Enums\DocumentCategory;
use App\Enums\LabOrderStatus;
use App\Enums\LabWorkType;
use App\Enums\Permission;
use App\Enums\PrescriptionType;
use App\Enums\TreatmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PatientPresenter;
use App\Models\Allergy;
use App\Models\Consultation;
use App\Models\Document;
use App\Models\LabOrder;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\TreatmentItem;
use App\Support\EnumOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentos del paciente: radiografías y fotos, órdenes a laboratorio y recetas.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly PatientPresenter $presenter) {}

    public function index(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewDocuments', $patient);

        $user = $request->user('web');
        $canSeeClinicalFiles = $user->can(Permission::DocumentsView->value);

        return Inertia::render('patients/documents', [
            'header' => $this->presenter->header($patient, $user),
            'documents' => $canSeeClinicalFiles ? $patient->documents()->with('uploader:id,name')->latest('id')->get()->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'category' => $d->category->label(),
                'description' => $d->description,
                'tooth_code' => $d->tooth_code,
                'taken_on' => $d->taken_on?->toDateString(),
                'is_image' => $d->isImage(),
                'size' => $d->size,
                'uploaded_by' => $d->uploader->name,
                'created_at' => $d->created_at?->toIso8601String(),
                'url' => route('patients.documents.file', [$patient, $d]),
            ]) : null,
            'lab_orders' => $patient->labOrders()->latest('sent_on')->latest('id')->get()->map(fn (LabOrder $o) => [
                'id' => $o->id,
                'laboratory' => $o->laboratory,
                'work_type' => $o->work_type->label(),
                'teeth' => $o->teeth,
                'shade' => $o->shade,
                'instructions' => $o->instructions,
                'sent_on' => $o->sent_on->toDateString(),
                'due_on' => $o->due_on->toDateString(),
                'received_on' => $o->received_on?->toDateString(),
                'delivered_on' => $o->delivered_on?->toDateString(),
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'overdue' => $o->isOverdue(),
                'transitions' => array_map(fn (LabOrderStatus $s) => ['value' => $s->value, 'label' => $s->actionLabel()], $o->status->allowedTransitions()),
            ]),
            'prescriptions' => $user->can('viewPrescriptions', $patient) ? $patient->prescriptions()->with('professional:id,name')->latest('issued_at')->get()->map(fn (Prescription $p) => [
                'id' => $p->id,
                'type' => $p->type->label(),
                'issued_at' => $p->issued_at->format('Y-m-d H:i'),
                'professional' => $p->professional->name,
                'summary' => $p->type === PrescriptionType::Prescription
                    ? collect($p->medications ?? [])->pluck('drug')->implode(', ')
                    : str((string) $p->instructions)->limit(80)->value(),
                'pdf_url' => route('patients.prescriptions.pdf', [$patient, $p]),
            ]) : null,
            'options' => [
                'categories' => EnumOptions::for(DocumentCategory::class),
                'work_types' => EnumOptions::for(LabWorkType::class),
                'consultations' => $patient->consultations()->latest('consulted_at')->limit(15)->get(['id', 'consulted_at'])
                    ->map(fn (Consultation $c) => ['id' => $c->id, 'label' => 'Consulta del '.$c->consulted_at->format('d/m/Y H:i')]),
                'treatment_items' => TreatmentItem::query()->with('procedure:id,name')->where('patient_id', $patient->id)
                    ->whereIn('status', [TreatmentStatus::Accepted, TreatmentStatus::InProgress])->get()
                    ->map(fn (TreatmentItem $i) => ['id' => $i->id, 'label' => $i->procedure->name.($i->tooth_code ? " · {$i->tooth_code}" : '')]),
                'max_mb' => round((int) config('clinic.documents.max_kb') / 1024),
            ],
            'allergies' => Allergy::query()->whereBelongsTo($patient)->active()->get()->map(fn (Allergy $a) => $a->allergen.' ('.$a->severity->label().')'),
            'can' => [
                'upload' => $user->can('uploadDocuments', $patient),
                'lab' => $user->can('manageLabOrders', $patient),
                'prescribe' => $user->can('issuePrescriptions', $patient),
            ],
        ]);
    }

    public function store(Request $request, Patient $patient, StoreDocument $action): RedirectResponse
    {
        Gate::authorize('uploadDocuments', $patient);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('clinic.documents.max_kb'), 'mimetypes:'.implode(',', (array) config('clinic.documents.mimes'))],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'tooth_code' => ['nullable', 'integer', Rule::exists('teeth', 'code')],
            'taken_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'consultation_id' => ['nullable', 'integer', Rule::exists('consultations', 'id')->where('patient_id', $patient->id)],
        ], [
            'file.mimetypes' => 'Solo se aceptan imágenes JPG, PNG o WebP y documentos PDF.',
            'file.max' => 'El archivo supera el tamaño máximo de '.round((int) config('clinic.documents.max_kb') / 1024).' MB.',
        ], ['file' => 'archivo', 'category' => 'tipo', 'title' => 'título', 'description' => 'descripción', 'tooth_code' => 'diente', 'taken_on' => 'fecha']);

        $action->handle(
            $patient,
            $request->user('web'),
            $request->file('file'),
            DocumentCategory::from($data['category']),
            $data['title'],
            $data['description'] ?? null,
            isset($data['tooth_code']) ? (int) $data['tooth_code'] : null,
            isset($data['taken_on']) ? (CarbonImmutable::createFromFormat('!Y-m-d', $data['taken_on']) ?: null) : null,
            isset($data['consultation_id']) ? Consultation::query()->whereKey($data['consultation_id'])->first() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento guardado.']);

        return back();
    }

    /** El archivo solo se entrega por esta ruta autorizada, sin caché. */
    public function file(Patient $patient, Document $document): StreamedResponse
    {
        Gate::authorize('view', $patient);
        abort_unless(request()->user('web')?->can(Permission::DocumentsView->value), 403);
        abort_unless(Storage::disk(StoreDocument::DISK)->exists($document->path), 404);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);

        return Storage::disk(StoreDocument::DISK)->response($document->path, "documento-{$document->id}.{$extension}", [
            'Content-Type' => $document->mime,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'",
        ], 'inline');
    }

    /** Archivar (no se borra: queda en la base y en el disco). */
    public function archive(Patient $patient, Document $document): RedirectResponse
    {
        Gate::authorize('uploadDocuments', $patient);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento archivado.']);

        return back();
    }

    public function storeLabOrder(Request $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('manageLabOrders', $patient);

        $data = $request->validate([
            'laboratory' => ['required', 'string', 'max:100'],
            'work_type' => ['required', Rule::enum(LabWorkType::class)],
            'treatment_item_id' => ['nullable', 'integer', Rule::exists('treatment_items', 'id')->where('patient_id', $patient->id)],
            'teeth' => ['nullable', 'string', 'max:60'],
            'shade' => ['nullable', 'string', 'max:20'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'sent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:sent_on'],
        ], ['due_on.after_or_equal' => 'La entrega estimada no puede ser anterior al envío.'], [
            'laboratory' => 'laboratorio', 'work_type' => 'tipo de trabajo', 'teeth' => 'piezas', 'shade' => 'color',
            'instructions' => 'indicaciones', 'sent_on' => 'fecha de envío', 'due_on' => 'entrega estimada',
        ]);

        $patient->labOrders()->create([...$data, 'status' => LabOrderStatus::Sent, 'created_by' => $request->user('web')->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Orden de laboratorio registrada.']);

        return back();
    }

    public function labOrderStatus(Request $request, Patient $patient, LabOrder $labOrder): RedirectResponse
    {
        Gate::authorize('manageLabOrders', $patient);

        $target = $request->validate(['status' => ['required', Rule::enum(LabOrderStatus::class)]])['status'];
        $target = LabOrderStatus::from($target);

        abort_unless(in_array($target, $labOrder->status->allowedTransitions(), true), 422, 'Cambio de estado no permitido.');

        $labOrder->update(array_filter([
            'status' => $target,
            'received_on' => $target === LabOrderStatus::Received ? today() : null,
            'delivered_on' => $target === LabOrderStatus::Delivered ? today() : null,
        ], fn ($v) => $v !== null));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Orden: '.mb_strtolower($target->label()).'.']);

        return back();
    }

    public function storePrescription(Request $request, Patient $patient, IssuePrescription $action): RedirectResponse
    {
        Gate::authorize('issuePrescriptions', $patient);

        $isPrescription = $request->input('type') === PrescriptionType::Prescription->value;
        $data = $request->validate([
            'type' => ['required', Rule::enum(PrescriptionType::class)],
            'medications' => [$isPrescription ? 'required' : 'prohibited', 'array', 'min:1', 'max:10'],
            'medications.*.drug' => ['required', 'string', 'max:120'],
            'medications.*.dose' => ['required', 'string', 'max:60'],
            'medications.*.frequency' => ['required', 'string', 'max:60'],
            'medications.*.duration' => ['required', 'string', 'max:60'],
            'instructions' => [$isPrescription ? 'nullable' : 'required', 'string', 'max:3000'],
            'allergies_acknowledged' => ['boolean'],
            'consultation_id' => ['nullable', 'integer', Rule::exists('consultations', 'id')->where('patient_id', $patient->id)],
        ], ['medications.required' => 'Agregue al menos un medicamento.'], [
            'medications.*.drug' => 'medicamento', 'medications.*.dose' => 'dosis', 'medications.*.frequency' => 'frecuencia',
            'medications.*.duration' => 'duración', 'instructions' => 'indicaciones',
        ]);

        /** @var list<array{drug: string, dose: string, frequency: string, duration: string}> $medications */
        $medications = array_values($data['medications'] ?? []);

        $action->handle(
            $patient,
            $request->user('web'),
            PrescriptionType::from($data['type']),
            $medications,
            $data['instructions'] ?? null,
            $request->boolean('allergies_acknowledged'),
            isset($data['consultation_id']) ? (int) $data['consultation_id'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Documento emitido. Ya puede imprimirlo.']);

        return back();
    }

    public function prescriptionPdf(Patient $patient, Prescription $prescription): HttpResponse
    {
        Gate::authorize('viewPrescriptions', $patient);

        return self::prescriptionResponse($patient, $prescription);
    }

    /** Compartido con el portal del paciente. */
    public static function prescriptionResponse(Patient $patient, Prescription $prescription): HttpResponse
    {
        $prescription->load('professional:id,name,license_number');

        $response = Pdf::loadView('pdf.prescription', [
            'prescription' => $prescription,
            'patient' => $patient,
            'clinic' => ['name' => config('app.name'), 'address' => config('clinic.address'), 'phone' => config('clinic.phone')],
        ])->setPaper('a5')->stream("{$prescription->type->value}-{$prescription->id}.pdf");

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
