<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Password;

/**
 * Envía el enlace para crear (o restablecer) la contraseña. Usa el broker de
 * contraseñas de Laravel, por lo que el enlace vence según auth.passwords.
 */
class SendPasswordSetupLink
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user): string
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        $this->audit->record('password_link_sent', $user);

        return $status;
    }
}
