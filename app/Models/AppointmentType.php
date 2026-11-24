<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $duration_minutes
 * @property string $color
 * @property bool $is_cleaning
 * @property bool $is_active
 */
class AppointmentType extends Model
{
    use Auditable;

    protected $fillable = ['name', 'duration_minutes', 'color', 'is_cleaning', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_cleaning' => 'boolean', 'duration_minutes' => 'integer'];
    }

    /** @param  Builder<AppointmentType>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
