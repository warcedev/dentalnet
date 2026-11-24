<?php

namespace App\Http\Middleware;

use App\Models\PatientAccount;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => fn () => $this->authProps($request),
            'portal' => fn () => ($account = $request->user('patient')) instanceof PatientAccount
                ? ['name' => $account->patient?->full_name, 'email' => $account->email]
                : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Datos mínimos del usuario y sus permisos efectivos. El frontend los usa
     * solo para mostrar u ocultar opciones; la autorización real la hacen las
     * Policies en el servidor.
     *
     * @return array<string, mixed>
     */
    private function authProps(Request $request): array
    {
        $user = $request->user('web');

        if ($user === null) {
            return ['user' => null, 'roles' => [], 'permissions' => []];
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_enabled' => $user->hasConfirmedTwoFactor(),
            ],
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
