<?php

namespace App\Models\Concerns;

use App\Exceptions\ImmutableRecordException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registros clínicos que no se borran: se desactivan dejando quién, cuándo y por qué.
 *
 * @property bool $is_active
 * @property int|null $deactivated_by
 * @property CarbonImmutable|null $deactivated_at
 * @property string|null $deactivation_reason
 */
trait Deactivatable
{
    public static function bootDeactivatable(): void
    {
        static::deleting(fn ($model) => throw ImmutableRecordException::for(class_basename($model)));
    }

    public function deactivate(User $by, string $reason): void
    {
        $this->forceFill([
            'is_active' => false,
            'deactivated_by' => $by->id,
            'deactivated_at' => now(),
            'deactivation_reason' => $reason,
        ])->save();
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }
}
