<?php

namespace App\Http\Controllers\Agenda;

use App\Actions\Agenda\ChangeAppointmentStatus;
use App\Actions\Agenda\RescheduleAppointment;
use App\Actions\Agenda\ScheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\AppointmentRequest;
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    public function store(AppointmentRequest $request, ScheduleAppointment $action): RedirectResponse
    {
        $appointment = $action->handle($request->toData(), $request->user('web'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cita agendada para el '.$appointment->starts_at->format('d/m/Y \a \l\a\s H:i').'.']);

        return back();
    }

    public function update(AppointmentRequest $request, Appointment $appointment, RescheduleAppointment $action): RedirectResponse
    {
        $action->handle($appointment, $request->toData($appointment->patient_id));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cita reprogramada.']);

        return back();
    }

    public function status(Request $request, Appointment $appointment, ChangeAppointmentStatus $action): RedirectResponse
    {
        Gate::authorize('update', $appointment);

        $data = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
            'reason' => ['nullable', 'string', 'max:300'],
        ], attributes: ['status' => 'estado', 'reason' => 'motivo']);

        $target = AppointmentStatus::from($data['status']);
        $action->handle($appointment, $target, $data['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cita: '.mb_strtolower($target->label()).'.']);

        return back();
    }
}
