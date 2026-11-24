<?php

namespace App\Models\Concerns;

use App\Exceptions\ImmutableRecordException;

/**
 * Primera barrera de inmutabilidad (la segunda son los triggers de la base).
 */
trait IsImmutable
{
    public static function bootIsImmutable(): void
    {
        static::updating(fn ($model) => throw ImmutableRecordException::for(class_basename($model)));
        static::deleting(fn ($model) => throw ImmutableRecordException::for(class_basename($model)));
    }
}
