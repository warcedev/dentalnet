<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AuditView->value);
    }

    public function update(User $actor, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $actor, AuditLog $log): bool
    {
        return false;
    }
}
