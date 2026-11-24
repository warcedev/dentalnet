<?php

namespace App\Models;

use App\Enums\ToothCondition;
use App\Enums\ToothEventStatus;
use App\Enums\ToothSurface;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Evento del odontograma. Inmutable: las correcciones son eventos nuevos que
 * apuntan al anterior mediante corrects_event_id.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $tooth_code
 * @property ToothSurface|null $surface
 * @property ToothCondition $condition
 * @property ToothEventStatus $status
 * @property CarbonImmutable $detected_on
 * @property CarbonImmutable|null $treated_on
 * @property int $professional_id
 * @property int|null $consultation_id
 * @property string|null $notes
 * @property int|null $corrects_event_id
 * @property string|null $correction_reason
 * @property bool $is_void
 * @property CarbonImmutable $created_at
 */
class ToothEvent extends Model
{
    use Auditable, IsImmutable;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'created_at'];

    protected function casts(): array
    {
        return [
            'surface' => ToothSurface::class,
            'condition' => ToothCondition::class,
            'status' => ToothEventStatus::class,
            'detected_on' => 'date',
            'treated_on' => 'date',
            'notes' => 'encrypted',
            'correction_reason' => 'encrypted',
            'is_void' => 'boolean',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    /** @return BelongsTo<ToothEvent, $this> */
    public function corrects(): BelongsTo
    {
        return $this->belongsTo(ToothEvent::class, 'corrects_event_id');
    }

    /** @return HasOne<ToothEvent, $this> */
    public function correctedBy(): HasOne
    {
        return $this->hasOne(ToothEvent::class, 'corrects_event_id');
    }
}
