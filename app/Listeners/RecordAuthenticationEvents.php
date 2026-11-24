<?php

namespace App\Listeners;

use App\Models\PatientAccount;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Deja en la auditoría los eventos de acceso del personal.
 */
class RecordAuthenticationEvents
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->record('login', $event->user, userId: $event->user->id);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('logout', $event->user, userId: $event->user->id);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->record('login_failed', $user, new: [
            'email' => $event->credentials['email'] ?? null,
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->audit->record('login_locked', new: [
            'email' => $event->request->input('email'),
        ]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof PatientAccount) {
            $this->audit->record('portal_password_set', $event->user);

            return;
        }

        if ($event->user instanceof User) {
            $this->audit->record('password_reset', $event->user, userId: $event->user->id);
        }
    }
}
