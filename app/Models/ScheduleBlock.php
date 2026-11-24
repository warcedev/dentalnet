<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueo de horario. Sin profesional ni consultorio = bloquea toda la clínica.
 *
 * @property int $id
 * @property int|null $professional_id
 * @property int|null $room_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $reason
 * @property int|null $created_by
 */
class ScheduleBlock extends Model
{
    use Auditable;

    protected $fillable = ['professional_id', 'room_id', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @param  Builder<ScheduleBlock>  $query */
    public function scopeOverlapping(Builder $query, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $query->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->format('Y-m-d H:i:s'));
    }

    /**
     * Bloqueos que afectan a un profesional o a un consultorio (incluye los generales).
     *
     * @param  Builder<ScheduleBlock>  $query
     */
    public function scopeAffecting(Builder $query, int $professionalId, int $roomId): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('professional_id', $professionalId)
            ->orWhere('room_id', $roomId)
            ->orWhere(fn (Builder $g) => $g->whereNull('professional_id')->whereNull('room_id')));
    }

    /** @return BelongsTo<User, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
