<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Odontogram\CorrectToothEvent;
use App\Actions\Odontogram\RecordToothEvent;
use App\Enums\ToothCondition;
use App\Enums\ToothEventStatus;
use App\Enums\ToothSurface;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PatientPresenter;
use App\Http\Requests\Odontogram\CorrectToothEventRequest;
use App\Http\Requests\Odontogram\ToothEventRequest;
use App\Models\Patient;
use App\Models\Tooth;
use App\Models\ToothEvent;
use App\Services\Odontogram\OdontogramInsights;
use App\Services\Odontogram\OdontogramState;
use App\Support\EnumOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OdontogramController extends Controller
{
    public function __construct(
        private readonly PatientPresenter $presenter,
        private readonly OdontogramState $state,
        private readonly OdontogramInsights $insights,
    ) {}

    public function show(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewOdontogram', $patient);

        $validated = $request->validate(
            ['fecha' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']],
            ['fecha.before_or_equal' => 'No se puede ver el odontograma en una fecha futura.'],
            ['fecha' => 'fecha'],
        );

        $asOf = isset($validated['fecha'])
            ? (CarbonImmutable::createFromFormat('!Y-m-d', $validated['fecha']) ?: CarbonImmutable::today())
            : CarbonImmutable::today();

        /** @var Collection<int, ToothEvent> $events */
        $events = $patient->toothEvents()->with('professional:id,name')->orderBy('id')->get();
        $user = $request->user('web');

        return Inertia::render('patients/odontogram', [
            'header' => fn () => $this->presenter->header($patient, $user),
            'as_of' => $asOf->toDateString(),
            'is_today' => $asOf->isToday(),
            'chart' => $this->state->at($events, $asOf),
            'teeth' => fn () => Tooth::query()->orderBy('code')->get(['code', 'dentition', 'quadrant', 'position', 'arch', 'type']),
            'timeline' => fn () => $this->timelineDates($events),
            'events' => fn () => $this->presentEvents($events),
            'insights' => fn () => [
                'last_cleaning' => $this->insights->lastCleaning($events)?->toDateString(),
                'next_cleaning' => $this->insights->nextCleaning($events)?->toDateString(),
                'cleaning_overdue' => ($next = $this->insights->nextCleaning($events)) !== null && $next->isPast(),
                'last_by_tooth' => $this->insights->lastInterventionByTooth($events),
                'repairs' => $this->insights->treated($events)->take(50)->map(fn (ToothEvent $e) => [
                    'id' => $e->id,
                    'date' => $e->treated_on?->toDateString(),
                    'tooth_code' => $e->tooth_code,
                    'surface' => $e->surface?->labelFor($e->tooth_code),
                    'condition' => $e->condition->label(),
                    'professional' => $e->professional?->name,
                ])->values(),
            ],
            'options' => fn () => [
                'conditions' => array_map(fn (ToothCondition $c) => [
                    'value' => $c->value,
                    'label' => $c->label(),
                    'scope' => $c->scope()->value,
                ], ToothCondition::cases()),
                'statuses' => EnumOptions::for(ToothEventStatus::class),
                'surfaces' => array_map(fn (ToothSurface $s) => $s->value, ToothSurface::cases()),
            ],
            'can_record' => fn () => $user->can('recordOdontogram', $patient),
        ]);
    }

    public function store(ToothEventRequest $request, Patient $patient, RecordToothEvent $action): RedirectResponse
    {
        $event = $action->handle($patient, $request->user('web'), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->describe($event).' registrado.']);

        return to_route('patients.odontogram', $patient);
    }

    public function correct(CorrectToothEventRequest $request, Patient $patient, ToothEvent $toothEvent, CorrectToothEvent $action): RedirectResponse
    {
        $reason = $request->string('reason')->value();

        $request->boolean('void')
            ? $action->void($toothEvent, $request->user('web'), $reason)
            : $action->correct($toothEvent, $request->user('web'), $request->toData(), $reason);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->boolean('void') ? 'Registro anulado. Queda en el historial.' : 'Corrección registrada. El registro original queda en el historial.',
        ]);

        return to_route('patients.odontogram', $patient);
    }

    /**
     * Fechas en las que cambió el odontograma (para el deslizador).
     *
     * @param  Collection<int, ToothEvent>  $events
     * @return list<string>
     */
    private function timelineDates(Collection $events): array
    {
        return array_values($this->state->valid($events)
            ->filter(fn (ToothEvent $e) => $e->tooth_code !== null)
            ->flatMap(fn (ToothEvent $e) => array_filter([$e->detected_on->toDateString(), $e->treated_on?->toDateString()]))
            ->unique()
            ->sort()
            ->all());
    }

    /**
     * @param  Collection<int, ToothEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function presentEvents(Collection $events): array
    {
        $correctedIds = $events->pluck('corrects_event_id')->filter()->flip();

        return array_values($events->sortByDesc('id')->map(fn (ToothEvent $e) => [
            'id' => $e->id,
            'tooth_code' => $e->tooth_code,
            'surface' => $e->surface?->value,
            'surface_label' => $e->surface?->labelFor($e->tooth_code),
            'condition' => $e->condition->value,
            'condition_label' => $e->condition->label(),
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'detected_on' => $e->detected_on->toDateString(),
            'treated_on' => $e->treated_on?->toDateString(),
            'notes' => $e->notes,
            'professional' => $e->professional?->name,
            'created_at' => $e->created_at->toIso8601String(),
            'corrects_event_id' => $e->corrects_event_id,
            'correction_reason' => $e->correction_reason,
            'is_void' => $e->is_void,
            'is_superseded' => $correctedIds->has($e->id),
        ])->all());
    }

    private function describe(ToothEvent $event): string
    {
        $where = $event->tooth_code === null
            ? 'boca completa'
            : 'diente '.$event->tooth_code.($event->surface ? ' ('.$event->surface->labelFor($event->tooth_code).')' : '');

        return $event->condition->label().' en '.$where;
    }
}
