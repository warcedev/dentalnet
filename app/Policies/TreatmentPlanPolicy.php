<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TreatmentPlan;
use App\Models\User;

class TreatmentPlanPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::TreatmentsView->value) || $actor->can(Permission::PaymentsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::TreatmentsManage->value);
    }

    public function update(User $actor, TreatmentPlan $plan): bool
    {
        return $actor->can(Permission::TreatmentsManage->value);
    }

    public function delete(User $actor, TreatmentPlan $plan): bool
    {
        return false;
    }
}
