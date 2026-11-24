<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    /** Los usuarios nunca se borran: tienen historial clínico y de auditoría asociado. */
    public function delete(User $actor, User $target): bool
    {
        return false;
    }
}
