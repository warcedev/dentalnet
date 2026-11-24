<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalización para búsquedas que funcionan igual en SQLite, MySQL y
 * PostgreSQL: minúsculas, sin acentos y sin signos.
 */
final class SearchText
{
    public static function normalize(?string $value): string
    {
        $ascii = Str::ascii((string) $value);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9 ]/', ' ', strtolower($ascii))));
    }

    public static function digits(?string $value): string
    {
        return (string) preg_replace('/\D/', '', (string) $value);
    }

    /**
     * Escapa los comodines de LIKE para que el texto se busque literalmente.
     * Usar con `LIKE ? ESCAPE '!'`: "!" funciona igual en SQLite, MySQL y PostgreSQL.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
