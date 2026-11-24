<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $patient_id
 * @property string $title
 * @property int $professional_id
 * @property string|null $notes
 * @property CarbonImmutable|null $closed_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 */
class TreatmentPlan extends Model
{
    use Auditable;

    protected $fillable = ['patient_id', 'title', 'professional_id', 'notes', 'closed_at', 'created_by'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime', 'notes' => 'encrypted'];
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

    /** @return HasMany<TreatmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TreatmentItem::class);
    }

    /**
     * Alias que usa Laravel para verificar que el ítem de la URL pertenece al plan.
     *
     * @return HasMany<TreatmentItem, $this>
     */
    public function treatmentItems(): HasMany
    {
        return $this->items();
    }

    /** @return HasMany<Installment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
