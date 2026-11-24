<?php

namespace App\Models;

use App\Enums\LabOrderStatus;
use App\Enums\LabWorkType;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Orden a laboratorio (prótesis, coronas).
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $treatment_item_id
 * @property string $laboratory
 * @property LabWorkType $work_type
 * @property string|null $teeth
 * @property string|null $shade
 * @property string|null $instructions
 * @property CarbonImmutable $sent_on
 * @property CarbonImmutable $due_on
 * @property CarbonImmutable|null $received_on
 * @property CarbonImmutable|null $delivered_on
 * @property LabOrderStatus $status
 * @property int $created_by
 */
class LabOrder extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'work_type' => LabWorkType::class,
            'status' => LabOrderStatus::class,
            'instructions' => 'encrypted',
            'sent_on' => 'date',
            'due_on' => 'date',
            'received_on' => 'date',
            'delivered_on' => 'date',
        ];
    }

    /** Atrasado: todavía en el laboratorio y pasó la fecha estimada. */
    public function isOverdue(): bool
    {
        return $this->status === LabOrderStatus::Sent && $this->due_on->lt(CarbonImmutable::today());
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return BelongsTo<TreatmentItem, $this> */
    public function treatmentItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentItem::class);
    }
}
