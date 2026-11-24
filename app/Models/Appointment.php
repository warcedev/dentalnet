<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $patient_id
 * @property int $professional_id
 * @property int $room_id
 * @property int $appointment_type_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property AppointmentStatus $status
 * @property string|null $notes
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Appointment extends Model
{
    use Auditable;

    protected $fillable = [
        'patient_id', 'professional_id', 'room_id', 'appointment_type_id', 'starts_at', 'ends_at',
        'status', 'notes', 'confirmed_at', 'cancelled_at', 'cancellation_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'notes' => 'encrypted',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Citas que ocupan el horario y se superponen con el intervalo dado.
     * Dos intervalos se superponen si cada uno empieza antes de que termine el otro
     * (una cita que termina 10:00 no choca con otra que empieza 10:00).
     *
     * @param  Builder<Appointment>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $query->whereIn('status', array_map(fn (AppointmentStatus $s) => $s->value, AppointmentStatus::occupying()))
            ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->format('Y-m-d H:i:s'));
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
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

    /** @return BelongsTo<AppointmentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    /** @return MorphMany<Reminder, $this> */
    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }
}
