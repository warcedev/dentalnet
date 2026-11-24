<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ScheduleBlock;
use App\Models\User;

class ScheduleBlockPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AppointmentsView->value);
    }

    public function view(User $actor, ScheduleBlock $model): bool
    {
        return $actor->can(Permission::AppointmentsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::AppointmentsManage->value);
    }

    public function update(User $actor, ScheduleBlock $model): bool
    {
        return $actor->can(Permission::AppointmentsManage->value);
    }

    public function delete(User $actor, ScheduleBlock $model): bool
    {
        return $actor->can(Permission::AppointmentsManage->value);
    }
}
