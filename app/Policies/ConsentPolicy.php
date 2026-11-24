<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Consent;
use App\Models\User;

/** Plantillas de consentimiento: las administra quien configura el consultorio. */
class ConsentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function update(User $actor, Consent $consent): bool
    {
        return $actor->can(Permission::SettingsManage->value);
    }

    public function delete(User $actor, Consent $consent): bool
    {
        return false;
    }
}
