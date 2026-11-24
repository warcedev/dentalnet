<?php

namespace App\Models;

use App\Enums\SignerRelationship;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consentimiento firmado. Inmutable.
 *
 * @property int $id
 * @property int $patient_id
 * @property int $consent_id
 * @property string $consent_title
 * @property int $consent_version
 * @property string $body_snapshot
 * @property string $body_hash
 * @property string $signer_name
 * @property string $signer_document
 * @property SignerRelationship $signer_relationship
 * @property string $signature_path
 * @property string $signature_hash
 * @property int $witnessed_by
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $signed_at
 */
class ConsentSignature extends Model
{
    use Auditable, IsImmutable;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['signature_path'];

    protected function casts(): array
    {
        return [
            'signer_relationship' => SignerRelationship::class,
            'signed_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    protected function auditExclude(): array
    {
        return ['body_snapshot', 'user_agent'];
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Consent, $this> */
    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }

    /** @return BelongsTo<User, $this> */
    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witnessed_by');
    }
}
