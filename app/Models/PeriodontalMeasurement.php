<?php

namespace App\Models;

use App\Models\Concerns\IsImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Mediciones de un diente. Los arreglos tienen 6 valores, en el orden:
 * mesiovestibular, vestibular, distovestibular, mesiolingual, lingual, distolingual.
 *
 * @property int $id
 * @property int $periodontal_record_id
 * @property int $tooth_code
 * @property bool $is_missing
 * @property list<int|null>|null $probing_depth
 * @property list<int|null>|null $recession
 * @property list<bool>|null $bleeding
 * @property list<bool>|null $plaque
 * @property int|null $mobility
 */
class PeriodontalMeasurement extends Model
{
    use IsImmutable;

    public const SITES = ['MV', 'V', 'DV', 'ML', 'L', 'DL'];

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_missing' => 'boolean',
            'probing_depth' => 'array',
            'recession' => 'array',
            'bleeding' => 'array',
            'plaque' => 'array',
            'mobility' => 'integer',
        ];
    }
}
