<?php

namespace App\Models;

use App\Enums\ToothCondition;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $default_price
 * @property int $warranty_months
 * @property bool $requires_anesthesia
 * @property ToothCondition|null $tooth_condition
 * @property bool $is_active
 */
class Procedure extends Model
{
    use Auditable;

    protected $fillable = ['name', 'default_price', 'warranty_months', 'requires_anesthesia', 'tooth_condition', 'is_active'];

    protected function casts(): array
    {
        return [
            'default_price' => 'integer',
            'warranty_months' => 'integer',
            'requires_anesthesia' => 'boolean',
            'tooth_condition' => ToothCondition::class,
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<Procedure>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
