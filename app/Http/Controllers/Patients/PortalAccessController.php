<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Portal\InviteToPortal;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PortalAccessController extends Controller
{
    public function invite(Request $request, Patient $patient, InviteToPortal $action): RedirectResponse
    {
        Gate::authorize('managePortal', $patient);

        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']], attributes: ['email' => 'correo electrónico']);
        $account = $action->handle($patient, $data['email']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se envió la invitación al portal a {$account->email}."]);

        return back();
    }

    public function toggle(Patient $patient): RedirectResponse
    {
        Gate::authorize('managePortal', $patient);

        $account = $patient->portalAccount()->firstOrFail();
        $account->update(['is_active' => ! $account->is_active]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $account->is_active ? 'Acceso al portal reactivado.' : 'Acceso al portal desactivado.']);

        return back();
    }
}
