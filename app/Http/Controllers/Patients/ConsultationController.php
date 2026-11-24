<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Consultations\RecordConsultation;
use App\Enums\AppointmentStatus;
use App\Enums\TreatmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PatientPresenter;
use App\Http\Requests\Treatments\ConsultationRequest;
use App\Models\Allergy;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\ConsultationAddendum;
use App\Models\ConsultationProcedure;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\TreatmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationController extends Controller
{
    public function __construct(private readonly PatientPresenter $presenter) {}

    public function index(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewAny', Consultation::class);

        $user = $request->user('web');

        return Inertia::render('patients/consultations', [
            'header' => $this->presenter->header($patient, $user),
            'consultations' => $patient->consultations()
                ->with(['professional:id,name', 'procedures.procedure:id,name', 'addenda.author:id,name'])
                ->latest('consulted_at')
                ->get()
                ->map(fn (Consultation $c) => [
                    'id' => $c->id,
                    'consulted_at' => $c->consulted_at->format('Y-m-d H:i'),
                    'professional' => $c->professional->name,
                    'reason' => $c->reason,
                    'subjective' => $c->subjective,
                    'objective' => $c->objective,
                    'assessment' => $c->assessment,
                    'plan' => $c->plan,
                    'materials' => $c->materials,
                    'observations' => $c->observations,
                    'next_visit' => $c->next_visit,
                    'next_visit_on' => $c->next_visit_on?->toDateString(),
                    'anesthesia_used' => $c->anesthesia_used,
                    'allergies_acknowledged_at' => $c->allergies_acknowledged_at?->format('Y-m-d H:i'),
                    'procedures' => $c->procedures->map(fn (ConsultationProcedure $p) => [
                        'name' => $p->procedure->name,
                        'tooth_code' => $p->tooth_code,
                        'surface' => $p->surface?->labelFor($p->tooth_code),
                        'notes' => $p->notes,
                        'from_plan' => $p->treatment_item_id !== null,
                    ]),
                    'addenda' => $c->addenda->map(fn (ConsultationAddendum $a) => [
                        'id' => $a->id,
                        'body' => $a->body,
                        'author' => $a->author->name,
                        'created_at' => $a->created_at->format('Y-m-d H:i'),
                    ]),
                    'can_add_addendum' => $user->can('addAddendum', $c),
                ]),
            'options' => [
                'procedures' => Procedure::query()->active()->orderBy('name')->get(['id', 'name', 'requires_anesthesia', 'tooth_condition']),
                'pending_items' => TreatmentItem::query()
                    ->with('procedure:id,name,requires_anesthesia')
                    ->where('patient_id', $patient->id)
                    ->whereIn('status', [TreatmentStatus::Accepted, TreatmentStatus::InProgress])
                    ->orderBy('id')
                    ->get()
                    ->map(fn (TreatmentItem $i) => [
                        'id' => $i->id,
                        'label' => $i->procedure->name.($i->tooth_code ? " · diente {$i->tooth_code}" : '').($i->surface ? ' '.$i->surface->labelFor($i->tooth_code) : ''),
                        'requires_anesthesia' => $i->procedure->requires_anesthesia,
                    ]),
                'appointments' => Appointment::query()
                    ->where('patient_id', $patient->id)
                    ->whereDate('starts_at', today())
                    ->whereIn('status', array_map(fn ($s) => $s->value, AppointmentStatus::occupying()))
                    ->get(['id', 'starts_at'])
                    ->map(fn (Appointment $a) => ['id' => $a->id, 'label' => 'Cita de hoy '.$a->starts_at->format('H:i')]),
            ],
            'allergies' => Allergy::query()->whereBelongsTo($patient)->active()->get()->map(fn (Allergy $a) => $a->allergen.' ('.$a->severity->label().')'),
            'can_record' => $user->can('create', Consultation::class) && ! $patient->trashed(),
        ]);
    }

    public function store(ConsultationRequest $request, Patient $patient, RecordConsultation $action): RedirectResponse
    {
        $consultation = $action->handle($patient, $request->user('web'), $request->toData());
        $procedures = $consultation->procedures()->count();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Consulta registrada'.($procedures > 0 ? " con {$procedures} procedimiento(s). El odontograma y el plan se actualizaron." : '.'),
        ]);

        return back();
    }

    public function storeAddendum(Request $request, Patient $patient, Consultation $consultation): RedirectResponse
    {
        Gate::authorize('addAddendum', $consultation);

        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:3000']], attributes: ['body' => 'adenda']);
        $consultation->addenda()->create(['body' => $data['body'], 'created_by' => $request->user('web')->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Adenda agregada. La consulta original no se modificó.']);

        return back();
    }
}
