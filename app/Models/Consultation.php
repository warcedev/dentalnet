<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consulta (evolución clínica). Inmutable: se complementa con adendas.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $professional_id
 * @property int|null $appointment_id
 * @property CarbonImmutable $consulted_at
 * @property string $reason
 * @property string|null $subjective
 * @property string|null $objective
 * @property string|null $assessment
 * @property string|null $plan
 * @property string|null $materials
 * @property string|null $observations
 * @property string|null $next_visit
 * @property CarbonImmutable|null $next_visit_on
 * @property bool $anesthesia_used
 * @property CarbonImmutable|null $allergies_acknowledged_at
 * @property CarbonImmutable $created_at
 */
class Consultation extends Model
{
    use Auditable, IsImmutable;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'created_at'];

    protected function casts(): array
    {
        return [
            'consulted_at' => 'datetime',
            'reason' => 'encrypted',
            'subjective' => 'encrypted',
            'objective' => 'encrypted',
            'assessment' => 'encrypted',
            'plan' => 'encrypted',
            'materials' => 'encrypted',
            'observations' => 'encrypted',
            'next_visit_on' => 'date',
            'anesthesia_used' => 'boolean',
            'allergies_acknowledged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    /** @return HasMany<ConsultationProcedure, $this> */
    public function procedures(): HasMany
    {
        return $this->hasMany(ConsultationProcedure::class);
    }

    /** @return HasMany<ConsultationAddendum, $this> */
    public function addenda(): HasMany
    {
        return $this->hasMany(ConsultationAddendum::class)->orderBy('id');
    }
}
