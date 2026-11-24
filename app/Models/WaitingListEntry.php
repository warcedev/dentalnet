<?php

namespace App\Models;

use App\Enums\WaitingListStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $patient_id
 * @property int|null $appointment_type_id
 * @property int|null $professional_id
 * @property string|null $preference
 * @property string|null $notes
 * @property WaitingListStatus $status
 * @property int|null $appointment_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 */
class WaitingListEntry extends Model
{
    use Auditable;

    protected $table = 'waiting_list';

    protected $fillable = ['patient_id', 'appointment_type_id', 'professional_id', 'preference', 'notes', 'status', 'appointment_id', 'created_by'];

    protected function casts(): array
    {
        return ['status' => WaitingListStatus::class, 'notes' => 'encrypted'];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<AppointmentType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }

    /** @return BelongsTo<User, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }
}
