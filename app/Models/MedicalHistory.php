<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versión de los antecedentes médicos. Nunca se modifica: los cambios se
 * registran como una versión nueva.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $version
 * @property string|null $medical_background
 * @property string|null $surgical_history
 * @property string|null $current_medication
 * @property string|null $habits
 * @property string|null $observations
 * @property string|null $change_reason
 * @property int $recorded_by
 * @property CarbonImmutable $created_at
 */
class MedicalHistory extends Model
{
    use Auditable, IsImmutable;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'created_at'];

    protected function casts(): array
    {
        return [
            'medical_background' => 'encrypted',
            'surgical_history' => 'encrypted',
            'current_medication' => 'encrypted',
            'habits' => 'encrypted',
            'observations' => 'encrypted',
            'change_reason' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
