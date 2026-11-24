<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use App\Enums\ToothSurface;
use App\Enums\TreatmentStatus;
use App\Exceptions\ImmutableRecordException;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ítem de un plan de tratamiento. Regla 3: una vez completado no se modifica;
 * las correcciones son ajustes (TreatmentAdjustment) inmutables y auditados.
 *
 * @property int $id
 * @property int $treatment_plan_id
 * @property int $patient_id
 * @property int $procedure_id
 * @property string|null $description
 * @property int|null $tooth_code
 * @property ToothSurface|null $surface
 * @property int $price
 * @property int $warranty_months
 * @property TreatmentStatus $status
 * @property int|null $professional_id
 * @property int|null $consultation_id
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_on
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property int|null $created_by
 */
class TreatmentItem extends Model
{
    use Auditable;

    protected $fillable = [
        'treatment_plan_id', 'patient_id', 'procedure_id', 'description', 'tooth_code', 'surface', 'price',
        'warranty_months', 'status', 'professional_id', 'consultation_id', 'accepted_at', 'started_at',
        'completed_on', 'cancelled_at', 'cancellation_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'surface' => ToothSurface::class,
            'status' => TreatmentStatus::class,
            'price' => 'integer',
            'warranty_months' => 'integer',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_on' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (TreatmentItem $item) {
            if ($item->getOriginal('status') === TreatmentStatus::Completed) {
                throw new ImmutableRecordException('Un tratamiento completado no se puede editar. Registre un ajuste.');
            }
        });

        static::deleting(fn () => throw ImmutableRecordException::for('Tratamiento'));
    }

    /** Precio vigente: el último ajuste de precio, o el acordado. */
    public function effectivePrice(): int
    {
        $adjustment = $this->latestAdjustment(AdjustmentType::Price);

        return $adjustment !== null ? (int) $adjustment->new_value : $this->price;
    }

    public function effectiveWarrantyMonths(): int
    {
        $adjustment = $this->latestAdjustment(AdjustmentType::Warranty);

        return $adjustment !== null ? (int) $adjustment->new_value : $this->warranty_months;
    }

    /** Fin de la garantía (fecha de tratamiento + duración). */
    public function warrantyUntil(): ?CarbonImmutable
    {
        $months = $this->effectiveWarrantyMonths();

        return $this->status === TreatmentStatus::Completed && $this->completed_on !== null && $months > 0
            ? $this->completed_on->addMonthsNoOverflow($months)
            : null;
    }

    private function latestAdjustment(AdjustmentType $type): ?TreatmentAdjustment
    {
        $adjustments = $this->relationLoaded('adjustments') ? $this->adjustments : $this->adjustments()->get();

        return $adjustments->where('type', $type)->sortBy('id')->last();
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return BelongsTo<TreatmentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    /** @return BelongsTo<Procedure, $this> */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    /** @return BelongsTo<User, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    /** @return HasMany<TreatmentAdjustment, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(TreatmentAdjustment::class);
    }
}
