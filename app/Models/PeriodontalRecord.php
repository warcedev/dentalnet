<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Examen periodontal. Inmutable: un control nuevo es un registro nuevo.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $professional_id
 * @property CarbonImmutable $recorded_on
 * @property string|null $notes
 * @property CarbonImmutable $created_at
 */
class PeriodontalRecord extends Model
{
    use Auditable, IsImmutable;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'created_at'];

    protected function casts(): array
    {
        return ['recorded_on' => 'date', 'notes' => 'encrypted'];
    }

    /** @return HasMany<PeriodontalMeasurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(PeriodontalMeasurement::class)->orderBy('tooth_code');
    }

    /** @return BelongsTo<User, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }
}
