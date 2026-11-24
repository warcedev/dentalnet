<?php

namespace App\Support;

/**
 * Convierte enteros a palabras en español (para los recibos: "un millón
 * doscientos mil"). Soporta hasta 999.999.999.999.
 */
final class SpanishNumber
{
    private const UNITS = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
        'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte',
        'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];

    private const TENS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];

    private const HUNDREDS = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos',
        'setecientos', 'ochocientos', 'novecientos'];

    public static function toWords(int $number): string
    {
        if ($number === 0) {
            return 'cero';
        }

        if ($number < 0) {
            return 'menos '.self::toWords(-$number);
        }

        $parts = [];
        // En español no se usa "billón" para 10^9: se dice "mil millones".
        $millions = intdiv($number, 1_000_000);
        $belowMillion = $number % 1_000_000;

        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'un millón' : self::belowMillion($millions, apocope: true).' millones';
        }

        if ($belowMillion > 0) {
            $parts[] = self::belowMillion($belowMillion, apocope: false);
        }

        return implode(' ', $parts);
    }

    /** 1 a 999.999 ("tres mil cien", "veintiún mil"). */
    private static function belowMillion(int $n, bool $apocope): string
    {
        $thousands = intdiv($n, 1000);
        $rest = $n % 1000;
        $parts = [];

        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'mil' : self::belowThousand($thousands, apocope: true).' mil';
        }

        if ($rest > 0) {
            $parts[] = self::belowThousand($rest, $apocope);
        }

        return implode(' ', $parts);
    }

    /** "uno" → "un" delante de "mil" o "millones" (apócope). */
    private static function belowThousand(int $n, bool $apocope): string
    {
        if ($n === 100) {
            return 'cien';
        }

        $hundreds = intdiv($n, 100);
        $tensUnits = $n % 100;
        $words = [];

        if ($hundreds > 0) {
            $words[] = self::HUNDREDS[$hundreds];
        }

        if ($tensUnits > 0) {
            if ($tensUnits < 30) {
                $word = self::UNITS[$tensUnits];
            } else {
                $word = self::TENS[intdiv($tensUnits, 10)].($tensUnits % 10 > 0 ? ' y '.self::UNITS[$tensUnits % 10] : '');
            }

            if ($apocope) {
                $word = preg_replace(['/veintiuno$/', '/uno$/'], ['veintiún', 'un'], $word) ?? $word;
            }

            $words[] = $word;
        }

        return implode(' ', $words);
    }
}
