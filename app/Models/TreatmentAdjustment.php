<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $treatment_item_id
 * @property AdjustmentType $type
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string $reason
 * @property int $created_by
 * @property CarbonImmutable $created_at
 */
class TreatmentAdjustment extends Model
{
    use Auditable, IsImmutable;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'created_at'];

    protected function casts(): array
    {
        return ['type' => AdjustmentType::class];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
