<?php

namespace App\Models;

use App\Enums\ToothSurface;
use App\Models\Concerns\IsImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $consultation_id
 * @property int $procedure_id
 * @property int|null $treatment_item_id
 * @property int|null $tooth_code
 * @property ToothSurface|null $surface
 * @property string|null $notes
 */
class ConsultationProcedure extends Model
{
    use IsImmutable;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'created_at'];

    protected function casts(): array
    {
        return ['surface' => ToothSurface::class];
    }

    /** @return BelongsTo<Procedure, $this> */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }
}
