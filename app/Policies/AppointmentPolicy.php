<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AppointmentsView->value);
    }

    public function view(User $actor, Appointment $model): bool
    {
        return $actor->can(Permission::AppointmentsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::AppointmentsManage->value);
    }

    public function update(User $actor, Appointment $model): bool
    {
        return $actor->can(Permission::AppointmentsManage->value);
    }

    /** Las citas no se borran: se cancelan con motivo. */
    public function delete(User $actor, Appointment $model): bool
    {
        return false;
    }
}
