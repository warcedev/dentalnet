<?php

namespace App\Actions\Agenda;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Serializa las reservas: verificar disponibilidad y guardar ocurren bajo un
 * mismo candado para que dos personas no tomen el mismo horario a la vez.
 */
final class BookingLock
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        return Cache::lock('agenda-booking', 15)->block(10, fn () => DB::transaction($callback));
    }
}
