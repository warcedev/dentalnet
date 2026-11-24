<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versión de una plantilla de consentimiento informado.
 *
 * @property int $id
 * @property string $code
 * @property int $version
 * @property string $title
 * @property string $body
 * @property bool $is_active
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Consent extends Model
{
    use Auditable;

    protected $fillable = ['code', 'version', 'title', 'body', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Versión vigente de cada plantilla.
     *
     * @param  Builder<Consent>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return HasMany<ConsentSignature, $this> */
    public function signatures(): HasMany
    {
        return $this->hasMany(ConsentSignature::class);
    }
}
