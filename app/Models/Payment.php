<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Exceptions\ImmutableRecordException;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago. No se modifica ni se borra: solo se puede anular una vez, con motivo.
 *
 * @property int $id
 * @property int $patient_id
 * @property int|null $treatment_plan_id
 * @property int $receipt_number
 * @property int $amount
 * @property PaymentMethod $method
 * @property string|null $reference
 * @property CarbonImmutable $paid_at
 * @property string|null $notes
 * @property int $received_by
 * @property CarbonImmutable|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 */
class Payment extends Model
{
    use Auditable;

    private const VOID_FIELDS = ['voided_at', 'voided_by', 'void_reason', 'updated_at'];

    protected $fillable = [
        'patient_id', 'treatment_plan_id', 'receipt_number', 'amount', 'method', 'reference', 'paid_at', 'notes', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'receipt_number' => 'integer',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Payment $payment) {
            $onlyVoiding = array_diff(array_keys($payment->getDirty()), self::VOID_FIELDS) === [];

            if ($payment->getOriginal('voided_at') !== null || ! $onlyVoiding) {
                throw new ImmutableRecordException('Un pago no se modifica: anúlelo y registre uno nuevo.');
            }
        });

        static::deleting(fn () => throw ImmutableRecordException::for('Pago'));
    }

    /** @param  Builder<Payment>  $query */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return BelongsTo<TreatmentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
