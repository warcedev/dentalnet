<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $treatment_plan_id
 * @property int $number
 * @property CarbonImmutable $due_date
 * @property int $amount
 */
class Installment extends Model
{
    use Auditable;

    protected $fillable = ['treatment_plan_id', 'number', 'due_date', 'amount'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount' => 'integer', 'number' => 'integer'];
    }
}
