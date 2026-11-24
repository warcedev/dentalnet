<?php

namespace App\Models;

use App\Enums\ReminderChannelType;
use App\Enums\ReminderStatus;
use App\Enums\ReminderType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $patient_id
 * @property ReminderType $type
 * @property ReminderChannelType $channel
 * @property string|null $remindable_type
 * @property int|null $remindable_id
 * @property CarbonImmutable $scheduled_for
 * @property ReminderStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable|null $sent_at
 * @property string $idempotency_key
 * @property CarbonImmutable|null $created_at
 */
class Reminder extends Model
{
    protected $fillable = [
        'patient_id', 'type', 'channel', 'remindable_type', 'remindable_id', 'scheduled_for',
        'status', 'attempts', 'last_error', 'sent_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'channel' => ReminderChannelType::class,
            'status' => ReminderStatus::class,
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<ReminderLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }
}
