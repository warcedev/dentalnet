<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::ConsultationsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::ConsultationsRecord->value);
    }

    /** Solo el autor agrega adendas a su consulta (cada profesional edita sus notas). */
    public function addAddendum(User $actor, Consultation $consultation): bool
    {
        return $actor->can(Permission::ConsultationsRecord->value) && $consultation->professional_id === $actor->id;
    }

    public function update(User $actor, Consultation $consultation): bool
    {
        return false;
    }

    public function delete(User $actor, Consultation $consultation): bool
    {
        return false;
    }
}
