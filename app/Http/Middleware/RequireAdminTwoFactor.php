<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a los administradores a activar la verificación en dos pasos antes
 * de usar el sistema. Las rutas de configuración de seguridad quedan libres
 * para que puedan activarla.
 */
class RequireAdminTwoFactor
{
    /** @var list<string> */
    private const ALLOWED_ROUTES = [
        'security.edit',
        'password.confirm',
        'password.confirmation',
        'password.confirm.store',
        'two-factor.*',
        'passkey.*',
        'logout',
        'appearance.edit',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if (
            config('clinic.require_admin_2fa')
            && $user?->isAdmin()
            && ! $user->hasConfirmedTwoFactor()
            && ! $request->routeIs(...self::ALLOWED_ROUTES)
        ) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Como administrador, debe activar la verificación en dos pasos para continuar.',
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
