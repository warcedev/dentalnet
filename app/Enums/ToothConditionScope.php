<?php

namespace App\Enums;

enum ToothConditionScope: string
{
    /** Se registra sobre una superficie. */
    case Surface = 'superficie';
    /** Se registra sobre el diente completo. */
    case Tooth = 'diente';
    /** Procedimiento de toda la boca (sin diente). */
    case Mouth = 'boca';
}
