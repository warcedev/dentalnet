<?php

namespace App\Http\Controllers\Agenda;

use App\Enums\Role;
use App\Enums\WaitingListStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\WaitingListRequest;
use App\Models\AppointmentType;
use App\Models\Patient;
use App\Models\User;
use App\Models\WaitingListEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WaitingListController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', WaitingListEntry::class);

        $showAll = $request->boolean('todos');

        return Inertia::render('agenda/waiting-list', [
            'entries' => WaitingListEntry::query()
                ->with(['patient', 'type:id,name', 'professional:id,name'])
                ->when(! $showAll, fn ($q) => $q->where('status', WaitingListStatus::Waiting))
                ->orderBy('created_at')
                ->get()
                ->map(fn (WaitingListEntry $e) => [
                    'id' => $e->id,
                    'patient' => ['id' => $e->patient_id, 'name' => $e->patient->full_name, 'phone' => $e->patient->phone],
                    'type' => $e->type?->name,
                    'professional' => $e->professional?->name,
                    'preference' => $e->preference,
                    'notes' => $e->notes,
                    'status' => $e->status->value,
                    'status_label' => $e->status->label(),
                    'created_at' => $e->created_at?->toIso8601String(),
                ]),
            'show_all' => $showAll,
            'types' => AppointmentType::query()->active()->orderBy('name')->get(['id', 'name']),
            'professionals' => User::role(Role::Dentist->value)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'can_manage' => $request->user('web')->can('create', WaitingListEntry::class),
            'patient_options' => Inertia::optional(fn () => Patient::query()->search((string) $request->query('buscar_paciente'))
                ->orderBy('last_name')->limit(10)->get()
                ->map(fn (Patient $p) => ['id' => $p->id, 'name' => $p->full_name, 'document' => $p->document_number, 'phone' => $p->phone])),
        ]);
    }

    public function store(WaitingListRequest $request): RedirectResponse
    {
        WaitingListEntry::create([...$request->validated(), 'status' => WaitingListStatus::Waiting, 'created_by' => $request->user('web')->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Paciente agregado a la lista de espera.']);

        return back();
    }

    public function discard(WaitingListEntry $waitingListEntry): RedirectResponse
    {
        Gate::authorize('update', $waitingListEntry);

        $waitingListEntry->update(['status' => WaitingListStatus::Discarded]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Quitado de la lista de espera.']);

        return back();
    }
}
