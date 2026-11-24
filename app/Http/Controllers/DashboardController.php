<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Presenters\AppointmentPresenter;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AppointmentPresenter $presenter): Response
    {
        $user = $request->user('web');
        $canSeeAgenda = $user->can(Permission::AppointmentsView->value);

        // El odontólogo ve sus propias citas; recepción y administración, todas.
        $onlyMine = $user->hasRole(Role::Dentist->value) && ! $user->isAdmin();

        $today = $canSeeAgenda
            ? Appointment::query()
                ->with(['patient', 'professional:id,name', 'room:id,name', 'type:id,name,color'])
                ->whereDate('starts_at', today())
                ->whereIn('status', array_map(fn ($s) => $s->value, [...AppointmentStatus::occupying(), AppointmentStatus::NoShow]))
                ->when($onlyMine, fn ($q) => $q->where('professional_id', $user->id))
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Appointment $a) => $presenter->present($a))
            : null;

        return Inertia::render('dashboard', [
            'today_appointments' => $today,
            'only_mine' => $onlyMine,
        ]);
    }
}
