<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Procedure;
use App\Models\User;

class ProcedurePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function update(User $actor, Procedure $procedure): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function delete(User $actor, Procedure $procedure): bool
    {
        return false;
    }
}
