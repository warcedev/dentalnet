<?php

namespace App\Http\Middleware;

use App\Models\PatientAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Corta la sesión del portal si la cuenta se desactivó o el paciente fue archivado. */
class EnsurePortalAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = Auth::guard('patient')->user();

        if ($account instanceof PatientAccount && (! $account->is_active || $account->patient()->withTrashed()->first()?->trashed())) {
            Auth::guard('patient')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')->withErrors(['email' => 'Su acceso al portal no está activo. Comuníquese con el consultorio.']);
        }

        return $next($request);
    }
}
