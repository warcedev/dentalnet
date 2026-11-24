<?php

namespace App\Http\Controllers\Agenda;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Presenters\AppointmentPresenter;
use App\Models\Allergy;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\Room;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Models\WaitingListEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgendaController extends Controller
{
    public function __construct(private readonly AppointmentPresenter $presenter) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Appointment::class);

        $filters = $request->validate([
            'vista' => ['nullable', 'in:dia,semana,mes,lista'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'agrupar' => ['nullable', 'in:profesional,consultorio'],
            'profesional' => ['nullable', 'integer'],
            'consultorio' => ['nullable', 'integer'],
            'paciente' => ['nullable', 'integer'],
            'espera' => ['nullable', 'integer'],
            'buscar_paciente' => ['nullable', 'string', 'max:60'],
        ]);

        $view = $filters['vista'] ?? 'dia';
        $date = isset($filters['fecha']) ? (CarbonImmutable::createFromFormat('!Y-m-d', $filters['fecha']) ?: CarbonImmutable::today()) : CarbonImmutable::today();
        [$from, $to] = $this->range($view, $date);
        $professionalId = $filters['profesional'] ?? null;
        $roomId = $filters['consultorio'] ?? null;
        $user = $request->user('web');

        $appointments = Appointment::query()
            ->with(['patient', 'professional:id,name', 'room:id,name', 'type:id,name,color'])
            ->where('starts_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $to->format('Y-m-d H:i:s'))
            ->when($view !== 'dia' && $professionalId, fn ($q) => $q->where('professional_id', $professionalId))
            ->when($view !== 'dia' && $roomId, fn ($q) => $q->where('room_id', $roomId))
            ->orderBy('starts_at')
            ->get();

        $withAllergies = $user->can(Permission::ClinicalAlertsView->value)
            ? Allergy::query()->whereIn('patient_id', $appointments->pluck('patient_id'))->where('is_active', true)
                ->distinct()->pluck('patient_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all()
            : [];

        return Inertia::render('agenda/index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'range' => ['from' => $from->toDateString(), 'to' => $to->subDay()->toDateString()],
            'filters' => [
                'agrupar' => $filters['agrupar'] ?? 'profesional',
                'profesional' => $professionalId,
                'consultorio' => $roomId,
            ],
            'appointments' => $appointments->map(fn (Appointment $a) => $this->presenter->present($a, $withAllergies))->values(),
            'blocks' => ScheduleBlock::query()
                ->with(['professional:id,name', 'room:id,name'])
                ->overlapping($from, $to)
                ->orderBy('starts_at')
                ->get()
                ->map(fn (ScheduleBlock $b) => [
                    'id' => $b->id,
                    'starts_at' => $b->starts_at->format('Y-m-d H:i'),
                    'ends_at' => $b->ends_at->format('Y-m-d H:i'),
                    'reason' => $b->reason,
                    'professional' => $b->professional ? ['id' => $b->professional->id, 'name' => $b->professional->name] : null,
                    'room' => $b->room ? ['id' => $b->room->id, 'name' => $b->room->name] : null,
                ]),
            'professionals' => User::role(Role::Dentist->value)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'rooms' => Room::query()->active()->orderBy('name')->get(['id', 'name']),
            'types' => AppointmentType::query()->active()->orderBy('name')->get(['id', 'name', 'duration_minutes', 'color']),
            'config' => [
                'opens_at' => config('clinic.agenda.opens_at'),
                'closes_at' => config('clinic.agenda.closes_at'),
                'slot_minutes' => config('clinic.agenda.slot_minutes'),
                'working_days' => config('clinic.agenda.working_days'),
            ],
            'can_manage' => $user->can('create', Appointment::class),
            'prefill' => $this->prefill($filters['paciente'] ?? null, $filters['espera'] ?? null),
            'patient_options' => Inertia::optional(fn () => $this->searchPatients((string) ($filters['buscar_paciente'] ?? ''))),
        ]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} Rango [desde, hasta) */
    private function range(string $view, CarbonImmutable $date): array
    {
        return match ($view) {
            'semana' => [$date->startOfWeek(), $date->startOfWeek()->addWeek()],
            'mes' => [$date->startOfMonth()->startOfWeek(), $date->endOfMonth()->endOfWeek()->addDay()->startOfDay()],
            default => [$date->startOfDay(), $date->addDay()->startOfDay()],
        };
    }

    /** @return array<string, mixed>|null */
    private function prefill(?int $patientId, ?int $waitingId): ?array
    {
        if ($waitingId !== null) {
            $entry = WaitingListEntry::query()->with('patient')->find($waitingId);

            if ($entry !== null && $entry->patient !== null) {
                return [
                    'patient' => $this->patientOption($entry->patient),
                    'appointment_type_id' => $entry->appointment_type_id,
                    'professional_id' => $entry->professional_id,
                    'waiting_list_entry_id' => $entry->id,
                ];
            }
        }

        if ($patientId !== null && ($patient = Patient::query()->find($patientId)) !== null) {
            return ['patient' => $this->patientOption($patient)];
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function searchPatients(string $term): array
    {
        if (mb_strlen(trim($term)) < 2) {
            return [];
        }

        return array_values(Patient::query()->search($term)->orderBy('last_name')->limit(10)->get()
            ->map(fn (Patient $p) => $this->patientOption($p))->all());
    }

    /** @return array<string, mixed> */
    private function patientOption(Patient $p): array
    {
        return ['id' => $p->id, 'name' => $p->full_name, 'document' => $p->document_number, 'phone' => $p->phone];
    }
}
