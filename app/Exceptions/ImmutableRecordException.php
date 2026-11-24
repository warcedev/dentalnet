<?php

namespace App\Exceptions;

use LogicException;

/**
 * Se lanza al intentar modificar o borrar un registro que solo admite inserciones
 * (auditoría, eventos del odontograma, firmas de consentimiento).
 */
class ImmutableRecordException extends LogicException
{
    public static function for(string $model): self
    {
        return new self("Los registros de {$model} no se pueden modificar ni eliminar. Registre una corrección.");
    }
}
