<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Notifications\PortalInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Cuenta del portal del paciente. Solo da acceso a los datos de su paciente.
 *
 * @property int $id
 * @property int $patient_id
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property CarbonImmutable|null $invited_at
 * @property CarbonImmutable|null $last_login_at
 */
class PatientAccount extends Authenticatable implements CanResetPasswordContract
{
    use Auditable, CanResetPassword, Notifiable;

    protected $fillable = ['patient_id', 'email', 'password', 'is_active', 'invited_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    protected function auditExclude(): array
    {
        return ['last_login_at'];
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new PortalInvitation($token));
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
