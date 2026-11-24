<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Regla: el sistema nunca puede quedar sin un administrador activo, y un
 * administrador no puede desactivarse ni quitarse el rol a sí mismo.
 */
class EnsureAnActiveAdminRemains
{
    /** @param  list<string>  $newRoles */
    public function handle(User $actor, User $target, array $newRoles, bool $newIsActive): void
    {
        $losesAdmin = $target->isAdmin()
            && (! in_array(Role::Admin->value, $newRoles, true) || ! $newIsActive);

        if (! $losesAdmin) {
            return;
        }

        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'roles' => 'No puede quitarse a sí mismo el rol de administrador ni desactivar su propia cuenta.',
            ]);
        }

        $otherActiveAdmins = User::role(Role::Admin->value)
            ->where('is_active', true)
            ->whereKeyNot($target->getKey())
            ->count();

        if ($otherActiveAdmins === 0) {
            throw ValidationException::withMessages([
                'roles' => 'Debe quedar al menos un administrador activo.',
            ]);
        }
    }
}
