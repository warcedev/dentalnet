<?php

namespace App\Models;

use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de cada intento de envío.
 *
 * @property int $id
 * @property int $reminder_id
 * @property int $attempt
 * @property string $status
 * @property string $channel
 * @property string|null $message
 * @property CarbonImmutable $created_at
 */
class ReminderLog extends Model
{
    use IsImmutable;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'created_at'];
}
