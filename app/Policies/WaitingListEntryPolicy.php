<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\WaitingListEntry;

class WaitingListEntryPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AppointmentsView->value);
    }

    public function view(User $actor, WaitingListEntry $model): bool
    {
        return $actor->can(Permission::AppointmentsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::AppointmentsManage->value);
    }

    public function update(User $actor, WaitingListEntry $model): bool
    {
        return $actor->can(Permission::AppointmentsManage->value);
    }

    public function delete(User $actor, WaitingListEntry $model): bool
    {
        return $actor->can(Permission::AppointmentsManage->value);
    }
}
