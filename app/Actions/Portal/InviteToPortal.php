<?php

namespace App\Actions\Portal;

use App\Models\Patient;
use App\Models\PatientAccount;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea (o reactiva) la cuenta del portal y envía el enlace para crear la
 * contraseña. Nadie del consultorio conoce la contraseña del paciente.
 */
class InviteToPortal
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Patient $patient, string $email): PatientAccount
    {
        $email = Str::lower(trim($email));

        $taken = PatientAccount::query()->where('email', $email)->where('patient_id', '!=', $patient->id)->exists();

        if ($taken) {
            throw ValidationException::withMessages(['email' => 'Ese correo ya se usa para el portal de otro paciente.']);
        }

        $account = PatientAccount::query()->firstOrNew(['patient_id' => $patient->id]);

        if (! $account->exists) {
            $account->password = Str::password(40);
        }

        $account->fill(['email' => $email, 'is_active' => true, 'invited_at' => now()])->save();

        $status = Password::broker('patients')->sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => 'Ya se envió una invitación hace poco. Espere un minuto.']);
        }

        $this->audit->record('portal_invited', $account);

        return $account;
    }
}
