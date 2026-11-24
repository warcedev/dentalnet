<?php

namespace App\Models;

use App\Enums\ConditionType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\Deactivatable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $patient_id
 * @property ConditionType $condition
 * @property string|null $notes
 * @property int $recorded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PatientCondition extends Model
{
    use Auditable, Deactivatable;

    protected $fillable = ['patient_id', 'condition', 'notes', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'condition' => ConditionType::class,
            'notes' => 'encrypted',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'deactivation_reason' => 'encrypted',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
