<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Patients\SavePatient;
use App\Enums\AppointmentStatus;
use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Enums\Sex;
use App\Enums\SignerRelationship;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PatientPresenter;
use App\Http\Requests\Patients\PatientRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function __construct(private readonly PatientPresenter $presenter) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Patient::class);

        $user = $request->user('web');
        $search = trim((string) $request->query('buscar', ''));
        $archived = $request->boolean('archivados') && $user->can(Permission::PatientsArchive->value);
        $showAlerts = $user->can(Permission::ClinicalAlertsView->value);

        $patients = Patient::query()
            ->when($archived, fn ($q) => $q->onlyTrashed())
            ->search($search)
            ->when($showAlerts, fn ($q) => $q->withCount([
                'allergies as active_allergies_count' => fn ($a) => $a->where('is_active', true),
                'conditions as active_conditions_count' => fn ($c) => $c->where('is_active', true),
            ]))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Patient $p) => [
                'id' => $p->id,
                'full_name' => $p->last_name.', '.$p->first_name,
                'document' => $p->document_number,
                'age' => $p->age,
                'phone' => $p->phone,
                'allergies' => $showAlerts ? (int) $p->getAttribute('active_allergies_count') : null,
                'conditions' => $showAlerts ? (int) $p->getAttribute('active_conditions_count') : null,
            ]);

        return Inertia::render('patients/index', [
            'patients' => $patients,
            'filters' => ['buscar' => $search, 'archivados' => $archived],
            'can' => [
                'create' => $user->can('create', Patient::class),
                'view_archived' => $user->can(Permission::PatientsArchive->value),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Patient::class);

        return Inertia::render('patients/form', ['patient' => null, ...$this->options()]);
    }

    public function store(PatientRequest $request, SavePatient $action): RedirectResponse
    {
        $patient = $action->create($request->toData(), $request->user('web'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente registrado.']);

        return to_route('patients.show', $patient);
    }

    public function show(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);

        $user = $request->user('web');
        $canSeeAgenda = $user->can(Permission::AppointmentsView->value);

        return Inertia::render('patients/show', [
            'header' => $this->presenter->header($patient, $user),
            'upcoming' => $canSeeAgenda ? $patient->appointments()
                ->with(['professional:id,name', 'room:id,name', 'type:id,name,color'])
                ->where('starts_at', '>=', now()->startOfDay()->format('Y-m-d H:i:s'))
                ->whereIn('status', array_map(fn ($s) => $s->value, AppointmentStatus::occupying()))
                ->orderBy('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Appointment $a) => [
                    'id' => $a->id,
                    'starts_at' => $a->starts_at->format('Y-m-d H:i'),
                    'type' => $a->type->name,
                    'professional' => $a->professional->name,
                    'room' => $a->room->name,
                    'status_label' => $a->status->label(),
                ]) : null,
            'can_book' => $user->can(Permission::AppointmentsManage->value) && ! $patient->trashed(),
            'portal' => [
                'account' => ($account = $patient->portalAccount()->first()) ? [
                    'email' => $account->email,
                    'is_active' => $account->is_active,
                    'invited_at' => $account->invited_at?->toIso8601String(),
                    'last_login_at' => $account->last_login_at?->toIso8601String(),
                ] : null,
                'can_manage' => $user->can('managePortal', $patient),
            ],
            'patient' => $this->presenter->details($patient),
            'labels' => [
                'guardian_relationship' => $patient->guardian_relationship?->label(),
            ],
        ]);
    }

    public function edit(Request $request, Patient $patient): Response
    {
        Gate::authorize('update', $patient);

        return Inertia::render('patients/form', [
            'patient' => $this->presenter->details($patient),
            'header' => $this->presenter->header($patient, $request->user('web')),
            ...$this->options(),
        ]);
    }

    public function update(PatientRequest $request, Patient $patient, SavePatient $action): RedirectResponse
    {
        $action->update($patient, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datos del paciente actualizados.']);

        return to_route('patients.show', $patient);
    }

    /** Archiva (borrado lógico). El historial clínico se conserva. */
    public function destroy(Patient $patient): RedirectResponse
    {
        Gate::authorize('delete', $patient);

        $patient->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$patient->full_name} fue archivado. Su historial se conserva."]);

        return to_route('patients.index');
    }

    public function restore(Patient $patient): RedirectResponse
    {
        Gate::authorize('restore', $patient);

        $patient->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$patient->full_name} fue restaurado."]);

        return to_route('patients.show', $patient);
    }

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'options' => [
                'document_types' => EnumOptions::for(DocumentType::class),
                'sexes' => EnumOptions::for(Sex::class),
                'guardian_relationships' => array_values(array_filter(
                    EnumOptions::for(SignerRelationship::class),
                    fn ($o) => $o['value'] !== SignerRelationship::Patient->value,
                )),
            ],
        ];
    }
}
