<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pieza dentaria del catálogo FDI (solo lectura).
 *
 * @property int $code
 * @property string $dentition
 * @property int $quadrant
 * @property int $position
 * @property string $arch
 * @property string $type
 */
class Tooth extends Model
{
    protected $table = 'teeth';

    protected $primaryKey = 'code';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];
}
