<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Room;
use App\Models\User;

/** Configuración de la agenda: solo quien configura el consultorio. */
class RoomPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function update(User $actor, Room $model): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function delete(User $actor, Room $model): bool
    {
        return false;
    }
}
