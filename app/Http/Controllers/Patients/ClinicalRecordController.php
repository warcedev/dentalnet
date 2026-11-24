<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Clinical\RecordMedicalHistory;
use App\Enums\AllergyType;
use App\Enums\ConditionType;
use App\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PatientPresenter;
use App\Http\Requests\Clinical\AllergyRequest;
use App\Http\Requests\Clinical\ConditionRequest;
use App\Http\Requests\Clinical\DeactivateRecordRequest;
use App\Http\Requests\Clinical\MedicalHistoryRequest;
use App\Models\Allergy;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PatientCondition;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ficha clínica: antecedentes versionados, alergias y condiciones de riesgo.
 */
class ClinicalRecordController extends Controller
{
    public function __construct(private readonly PatientPresenter $presenter) {}

    public function show(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewClinicalRecord', $patient);

        $versions = $patient->medicalHistories()->with('recorder:id,name')->orderByDesc('version')->get();
        $requested = $request->integer('version');
        $selected = $requested > 0 ? $versions->firstWhere('version', $requested) : $versions->first();

        return Inertia::render('patients/clinical', [
            'header' => $this->presenter->header($patient, $request->user('web')),
            'history' => $selected ? $this->presentHistory($selected) : null,
            'latest_version' => $versions->first()?->version,
            'versions' => $versions->map(fn (MedicalHistory $h) => [
                'version' => $h->version,
                'created_at' => $h->created_at->toIso8601String(),
                'recorded_by' => $h->recorder?->name,
                'change_reason' => $h->change_reason,
            ]),
            'allergies' => $patient->allergies()->with(['recorder:id,name', 'deactivator:id,name'])->latest('id')->get()
                ->map(fn (Allergy $a) => [
                    'id' => $a->id,
                    'type' => $a->type->label(),
                    'allergen' => $a->allergen,
                    'severity' => $a->severity->value,
                    'severity_label' => $a->severity->label(),
                    'reaction' => $a->reaction,
                    ...$this->lifecycle($a),
                ]),
            'conditions' => $patient->conditions()->with(['recorder:id,name', 'deactivator:id,name'])->latest('id')->get()
                ->map(fn (PatientCondition $c) => [
                    'id' => $c->id,
                    'condition' => $c->condition->label(),
                    'high_risk' => $c->condition->isHighRisk(),
                    'notes' => $c->notes,
                    ...$this->lifecycle($c),
                ]),
            'options' => [
                'allergy_types' => EnumOptions::for(AllergyType::class),
                'severities' => EnumOptions::for(Severity::class),
                'conditions' => EnumOptions::for(ConditionType::class),
            ],
        ]);
    }

    public function storeHistory(MedicalHistoryRequest $request, Patient $patient, RecordMedicalHistory $action): RedirectResponse
    {
        /** @var array{medical_background?: ?string, surgical_history?: ?string, current_medication?: ?string, habits?: ?string, observations?: ?string, change_reason?: ?string} $data */
        $data = $request->validated();
        $history = $action->handle($patient, $request->user('web'), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Antecedentes guardados (versión {$history->version})."]);

        return to_route('patients.clinical', $patient);
    }

    public function storeAllergy(AllergyRequest $request, Patient $patient): RedirectResponse
    {
        $patient->allergies()->create([...$request->validated(), 'recorded_by' => $request->user('web')->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alergia registrada.']);

        return to_route('patients.clinical', $patient);
    }

    public function deactivateAllergy(DeactivateRecordRequest $request, Patient $patient, Allergy $allergy): RedirectResponse
    {
        $allergy->deactivate($request->user('web'), $request->string('reason')->value());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alergia marcada como inactiva. Queda en el historial.']);

        return to_route('patients.clinical', $patient);
    }

    public function storeCondition(ConditionRequest $request, Patient $patient): RedirectResponse
    {
        $patient->conditions()->create([...$request->validated(), 'recorded_by' => $request->user('web')->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Condición registrada.']);

        return to_route('patients.clinical', $patient);
    }

    public function deactivateCondition(DeactivateRecordRequest $request, Patient $patient, PatientCondition $condition): RedirectResponse
    {
        $condition->deactivate($request->user('web'), $request->string('reason')->value());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Condición marcada como inactiva. Queda en el historial.']);

        return to_route('patients.clinical', $patient);
    }

    /** @return array<string, mixed> */
    private function presentHistory(MedicalHistory $history): array
    {
        return [
            ...$history->only(['version', 'medical_background', 'surgical_history', 'current_medication', 'habits', 'observations', 'change_reason']),
            'created_at' => $history->created_at->toIso8601String(),
            'recorded_by' => $history->recorder?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function lifecycle(Allergy|PatientCondition $record): array
    {
        return [
            'is_active' => $record->is_active,
            'recorded_by' => $record->recorder?->name,
            'created_at' => $record->created_at?->toIso8601String(),
            'deactivated_by' => $record->deactivator?->name,
            'deactivated_at' => $record->deactivated_at?->toIso8601String(),
            'deactivation_reason' => $record->deactivation_reason,
        ];
    }
}
