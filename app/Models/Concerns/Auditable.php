<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Registra en la auditoría cada alta, modificación, baja y restauración del
 * modelo con sus valores anteriores y nuevos.
 *
 * - Los atributos de auditExclude(), los ocultos ($hidden) y las marcas de
 *   tiempo no se registran.
 * - Los atributos con cast "encrypted" se registran como "[cifrado]" para
 *   dejar constancia del cambio sin copiar el dato sensible a la auditoría.
 *
 * @mixin Model
 */
trait Auditable
{
    public const string MASK = '[cifrado]';

    public static function bootAuditable(): void
    {
        static::created(function (self $model) {
            $model->writeAudit('created', null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (self $model) {
            $changes = $model->auditableValues($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = $model->auditableValues(Arr::only($model->getRawOriginal(), array_keys($changes)));
            $model->writeAudit('updated', $old, $changes);
        });

        static::deleted(function (self $model) {
            $isSoft = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();
            $model->writeAudit($isSoft ? 'archived' : 'deleted', $model->auditableValues($model->getRawOriginal()), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (self $model) => $model->writeAudit('restored', null, null));
        }
    }

    /**
     * Atributos que el modelo no quiere registrar (además de los ocultos).
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function writeAudit(string $event, ?array $old, ?array $new): void
    {
        app(AuditLogger::class)->record($event, $this, $old, $new);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableValues(array $attributes): array
    {
        $excluded = array_merge(
            $this->getHidden(),
            $this->auditExclude(),
            [$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()],
        );

        $values = Arr::except($attributes, $excluded);

        foreach ($values as $key => $value) {
            if ($this->isEncryptedAttribute($key)) {
                $values[$key] = $value === null ? null : self::MASK;
            }
        }

        return $values;
    }

    private function isEncryptedAttribute(string $key): bool
    {
        $cast = $this->getCasts()[$key] ?? null;

        return is_string($cast) && str_starts_with($cast, 'encrypted');
    }
}
