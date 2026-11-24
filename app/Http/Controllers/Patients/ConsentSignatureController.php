<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Consents\ConsentRenderer;
use App\Actions\Consents\SignConsent;
use App\Enums\SignerRelationship;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PatientPresenter;
use App\Http\Requests\Clinical\SignConsentRequest;
use App\Models\Consent;
use App\Models\ConsentSignature;
use App\Models\Patient;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsentSignatureController extends Controller
{
    public function __construct(private readonly PatientPresenter $presenter) {}

    public function index(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewConsents', $patient);

        return Inertia::render('patients/consents/index', [
            'header' => $this->presenter->header($patient, $request->user('web')),
            'signatures' => $patient->consentSignatures()->with('witness:id,name')->latest('signed_at')->get()
                ->map(fn (ConsentSignature $s) => [
                    'id' => $s->id,
                    'title' => $s->consent_title,
                    'version' => $s->consent_version,
                    'signer_name' => $s->signer_name,
                    'signer_relationship' => $s->signer_relationship->label(),
                    'witness' => $s->witness?->name,
                    'signed_at' => $s->signed_at->toIso8601String(),
                ]),
        ]);
    }

    public function create(Request $request, Patient $patient, ConsentRenderer $renderer): Response
    {
        Gate::authorize('collectConsent', $patient);

        $user = $request->user('web');
        $guardianSigns = $patient->is_minor;

        return Inertia::render('patients/consents/sign', [
            'header' => $this->presenter->header($patient, $user),
            'templates' => Consent::query()->current()->orderBy('title')->get()->map(fn (Consent $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'version' => $c->version,
                'body' => $renderer->render($c, $patient, $user),
            ]),
            'defaults' => [
                'signer_name' => $guardianSigns ? (string) $patient->guardian_name : $patient->full_name,
                'signer_document' => $guardianSigns ? (string) $patient->guardian_document : $patient->document_number,
                'signer_relationship' => $guardianSigns
                    ? ($patient->guardian_relationship->value ?? SignerRelationship::Parent->value)
                    : SignerRelationship::Patient->value,
            ],
            'relationships' => EnumOptions::for(SignerRelationship::class),
            'preselected' => $request->integer('plantilla') ?: null,
        ]);
    }

    public function store(SignConsentRequest $request, Patient $patient, SignConsent $action): RedirectResponse
    {
        $consent = Consent::query()->findOrFail($request->integer('consent_id'));

        $signature = $action->handle($patient, $consent, $request->user('web'), [
            'signer_name' => $request->string('signer_name')->value(),
            'signer_document' => $request->string('signer_document')->value(),
            'signer_relationship' => $request->enum('signer_relationship', SignerRelationship::class) ?? SignerRelationship::Patient,
            'signature' => $request->string('signature')->value(),
        ], $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Consentimiento firmado y guardado.']);

        return to_route('patients.consents.show', [$patient, $signature]);
    }

    public function show(Request $request, Patient $patient, ConsentSignature $consentSignature): Response
    {
        $signature = $consentSignature;
        Gate::authorize('view', $patient);
        Gate::authorize('viewConsents', $patient);

        $signature->load('witness:id,name');

        return Inertia::render('patients/consents/show', [
            'header' => $this->presenter->header($patient, $request->user('web')),
            'signature' => [
                'id' => $signature->id,
                'title' => $signature->consent_title,
                'version' => $signature->consent_version,
                'body' => $signature->body_snapshot,
                'body_hash' => $signature->body_hash,
                'signature_hash' => $signature->signature_hash,
                'signer_name' => $signature->signer_name,
                'signer_document' => $signature->signer_document,
                'signer_relationship' => $signature->signer_relationship->label(),
                'witness' => $signature->witness?->name,
                'signed_at' => $signature->signed_at->toIso8601String(),
                'image_url' => route('patients.consents.image', [$patient, $signature]),
            ],
        ]);
    }

    /** Imagen de la firma: solo por esta ruta autorizada y sin caché. */
    public function image(Patient $patient, ConsentSignature $consentSignature): StreamedResponse
    {
        $signature = $consentSignature;
        Gate::authorize('view', $patient);
        Gate::authorize('viewConsents', $patient);

        abort_unless(Storage::disk(SignConsent::DISK)->exists($signature->signature_path), 404);

        return Storage::disk(SignConsent::DISK)->response($signature->signature_path, 'firma.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
