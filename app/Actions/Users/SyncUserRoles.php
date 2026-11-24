<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Asigna roles y deja constancia en la auditoría (spatie no dispara eventos
 * de modelo al cambiar roles).
 */
class SyncUserRoles
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  list<string>  $roles */
    public function handle(User $user, array $roles): void
    {
        $before = $user->getRoleNames()->sort()->values()->all();
        $after = collect($roles)->map(fn (string $r) => Role::from($r)->value)->unique()->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        $user->syncRoles($after);

        $this->audit->record('roles_updated', $user, ['roles' => $before], ['roles' => $after]);
    }
}
