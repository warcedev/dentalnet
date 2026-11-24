<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::RemindersManage->value);
    }

    public function update(User $actor, Reminder $reminder): bool
    {
        return $actor->can(Permission::RemindersManage->value);
    }
}
