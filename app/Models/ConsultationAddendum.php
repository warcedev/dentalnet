<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsImmutable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $consultation_id
 * @property string $body
 * @property int $created_by
 * @property CarbonImmutable $created_at
 */
class ConsultationAddendum extends Model
{
    use Auditable, IsImmutable;

    public const UPDATED_AT = null;

    protected $table = 'consultation_addenda';

    protected $guarded = ['id', 'created_at'];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
