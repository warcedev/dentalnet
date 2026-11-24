<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AppointmentTypeRequest;
use App\Http\Requests\Admin\RoomRequest;
use App\Models\AppointmentType;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgendaSettingsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', AppointmentType::class);

        return Inertia::render('admin/agenda-settings', [
            'types' => AppointmentType::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'duration_minutes', 'color', 'is_cleaning', 'is_active']),
            'rooms' => Room::query()->orderByDesc('is_active')->orderBy('name')->get(['id', 'name', 'is_active']),
            'config' => config('clinic.agenda'),
        ]);
    }

    public function storeType(AppointmentTypeRequest $request): RedirectResponse
    {
        AppointmentType::create([...$request->validated(), 'is_cleaning' => $request->boolean('is_cleaning'), 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tipo de cita creado.']);

        return back();
    }

    public function updateType(AppointmentTypeRequest $request, AppointmentType $appointmentType): RedirectResponse
    {
        $appointmentType->update([...$request->validated(), 'is_cleaning' => $request->boolean('is_cleaning'), 'is_active' => $request->boolean('is_active')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tipo de cita actualizado.']);

        return back();
    }

    public function storeRoom(RoomRequest $request): RedirectResponse
    {
        Room::create([...$request->validated(), 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Consultorio creado.']);

        return back();
    }

    public function updateRoom(RoomRequest $request, Room $room): RedirectResponse
    {
        $room->update([...$request->validated(), 'is_active' => $request->boolean('is_active')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Consultorio actualizado.']);

        return back();
    }
}
