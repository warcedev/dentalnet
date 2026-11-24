<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Consultorio (sillón o box dentro de la clínica).
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 */
class Room extends Model
{
    use Auditable;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @param  Builder<Room>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
