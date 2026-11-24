<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $actor, Payment $payment): bool
    {
        return $actor->can(Permission::PaymentsView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::PaymentsRegister->value);
    }

    /** Anular: solo administración. */
    public function update(User $actor, Payment $payment): bool
    {
        return $actor->can(Permission::PaymentsVoid->value);
    }

    public function delete(User $actor, Payment $payment): bool
    {
        return false;
    }
}
