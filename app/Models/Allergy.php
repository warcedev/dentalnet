<?php

namespace App\Models;

use App\Enums\AllergyType;
use App\Enums\Severity;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\Deactivatable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $patient_id
 * @property AllergyType $type
 * @property string $allergen
 * @property Severity $severity
 * @property string|null $reaction
 * @property int $recorded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Allergy extends Model
{
    use Auditable, Deactivatable;

    protected $fillable = ['patient_id', 'type', 'allergen', 'severity', 'reaction', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'type' => AllergyType::class,
            'severity' => Severity::class,
            'allergen' => 'encrypted',
            'reaction' => 'encrypted',
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
