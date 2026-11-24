<?php

namespace App\Http\Controllers\Agenda;

use App\Actions\Agenda\CreateScheduleBlock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\ScheduleBlockRequest;
use App\Models\ScheduleBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ScheduleBlockController extends Controller
{
    public function store(ScheduleBlockRequest $request, CreateScheduleBlock $action): RedirectResponse
    {
        $action->handle(
            $request->user('web'),
            $request->start(),
            $request->end(),
            $request->filled('professional_id') ? $request->integer('professional_id') : null,
            $request->filled('room_id') ? $request->integer('room_id') : null,
            $request->string('reason')->trim()->value(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horario bloqueado.']);

        return back();
    }

    public function destroy(ScheduleBlock $scheduleBlock): RedirectResponse
    {
        Gate::authorize('delete', $scheduleBlock);

        $scheduleBlock->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueo eliminado.']);

        return back();
    }
}
