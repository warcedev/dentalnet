<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\CreateStaffUser;
use App\Actions\Users\SendPasswordSetupLink;
use App\Actions\Users\UpdateStaffUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffUserRequest;
use App\Models\User;
use App\Support\SearchText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->query('buscar', ''));

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.SearchText::escapeLike($search).'%';
                $q->where(fn ($q) => $q
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", [$like]));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $this->present($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => ['buscar' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/form', [
            'user' => null,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(StaffUserRequest $request, CreateStaffUser $action): RedirectResponse
    {
        $action->handle($request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario creado. Se le envió un enlace para crear su contraseña.']);

        return to_route('admin.users.index');
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('admin/users/form', [
            'user' => $this->present($user->load('roles:id,name')),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(StaffUserRequest $request, User $user, UpdateStaffUser $action): RedirectResponse
    {
        $action->handle($request->user('web'), $user, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambios guardados.']);

        return to_route('admin.users.index');
    }

    public function sendPasswordLink(User $user, SendPasswordSetupLink $action): RedirectResponse
    {
        Gate::authorize('update', $user);

        $status = $action->handle($user);

        Inertia::flash('toast', $status === Password::RESET_LINK_SENT
            ? ['type' => 'success', 'message' => "Se envió el enlace a {$user->email}."]
            : ['type' => 'warning', 'message' => 'Ya se envió un enlace hace poco. Espere un minuto e intente de nuevo.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'license_number' => $user->license_number,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values(),
            'two_factor_enabled' => $user->hasConfirmedTwoFactor(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function roleOptions(): array
    {
        return array_map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()], Role::cases());
    }
}
