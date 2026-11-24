<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Exceptions\ImmutableRecordException;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $patient_id
 * @property string|null $documentable_type
 * @property int|null $documentable_id
 * @property int|null $tooth_code
 * @property DocumentCategory $category
 * @property string $title
 * @property string|null $description
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property string $sha256
 * @property CarbonImmutable|null $taken_on
 * @property int $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $deleted_at
 */
class Document extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'description' => 'encrypted',
            'taken_on' => 'date',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleting(fn () => throw ImmutableRecordException::for('Documento'));
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
