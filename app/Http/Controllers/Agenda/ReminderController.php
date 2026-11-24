<?php

namespace App\Http\Controllers\Agenda;

use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\ReminderLog;
use App\Services\Reminders\ReminderDispatcher;
use App\Services\Reminders\ReminderPlanner;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Reminder::class);

        $filters = $request->validate([
            'estado' => ['nullable', Rule::enum(ReminderStatus::class)],
            'tipo' => ['nullable', Rule::enum(ReminderType::class)],
        ]);

        $reminders = Reminder::query()
            ->with(['patient', 'logs' => fn ($q) => $q->latest('id')])
            ->when($filters['estado'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['tipo'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->orderByDesc('scheduled_for')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Reminder $r) => [
                'id' => $r->id,
                'patient' => ['id' => $r->patient_id, 'name' => $r->patient->full_name],
                'type' => $r->type->label(),
                'channel' => $r->channel->label(),
                'scheduled_for' => $r->scheduled_for->format('Y-m-d H:i'),
                'status' => $r->status->value,
                'status_label' => $r->status->label(),
                'attempts' => $r->attempts,
                'last_error' => $r->last_error,
                'sent_at' => $r->sent_at?->format('Y-m-d H:i'),
                'logs' => $r->logs->map(fn (ReminderLog $l) => [
                    'attempt' => $l->attempt,
                    'status' => $l->status,
                    'message' => $l->message,
                    'created_at' => $l->created_at->format('Y-m-d H:i'),
                ]),
            ]);

        $counts = Reminder::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('reminders/index', [
            'reminders' => $reminders,
            'filters' => ['estado' => $filters['estado'] ?? '', 'tipo' => $filters['tipo'] ?? ''],
            'counts' => collect(ReminderStatus::cases())->mapWithKeys(fn (ReminderStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'statuses' => EnumOptions::for(ReminderStatus::class),
            'types' => EnumOptions::for(ReminderType::class),
            'channel' => config('clinic.reminders.channel'),
        ]);
    }

    public function retry(Reminder $reminder): RedirectResponse
    {
        Gate::authorize('update', $reminder);

        abort_unless(in_array($reminder->status, [ReminderStatus::Failed, ReminderStatus::Skipped], true), 422, 'Solo se reintentan recordatorios fallidos u omitidos.');

        $reminder->update(['status' => ReminderStatus::Pending, 'scheduled_for' => now(), 'attempts' => 0]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'El recordatorio se volverá a enviar en el próximo ciclo.']);

        return back();
    }

    /** Ejecuta un ciclo ya (útil para no esperar a la tarea programada). */
    public function run(Request $request, ReminderPlanner $planner, ReminderDispatcher $dispatcher): RedirectResponse
    {
        Gate::authorize('viewAny', Reminder::class);

        $planned = array_sum($planner->planRecurring());
        $queued = $dispatcher->dispatchDue();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se planificaron {$planned} recordatorios nuevos y se encolaron {$queued} para enviar."]);

        return back();
    }
}
