<?php

namespace App\Models;

use App\Enums\PrescriptionType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Receta o indicaciones. Inmutable una vez emitida.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $professional_id
 * @property int|null $consultation_id
 * @property PrescriptionType $type
 * @property \ArrayObject<int, array{drug: string, dose: string, frequency: string, duration: string}>|null $medications
 * @property string|null $instructions
 * @property CarbonImmutable|null $allergies_acknowledged_at
 * @property CarbonImmutable $issued_at
 */
class Prescription extends Model
{
    use Auditable, IsImmutable;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'created_at'];

    protected function casts(): array
    {
        return [
            'type' => PrescriptionType::class,
            'medications' => AsEncryptedArrayObject::class,
            'instructions' => 'encrypted',
            'allergies_acknowledged_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    protected function auditExclude(): array
    {
        return ['medications'];
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
}
