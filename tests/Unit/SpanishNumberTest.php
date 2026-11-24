<?php

use App\Support\SpanishNumber;

it('convierte montos en guaraníes a palabras', function (int $n, string $words) {
    expect(SpanishNumber::toWords($n))->toBe($words);
})->with([
    [0, 'cero'],
    [1, 'uno'],
    [16, 'dieciséis'],
    [21, 'veintiuno'],
    [45, 'cuarenta y cinco'],
    [100, 'cien'],
    [101, 'ciento uno'],
    [500, 'quinientos'],
    [1000, 'mil'],
    [21000, 'veintiún mil'],
    [31000, 'treinta y un mil'],
    [101000, 'ciento un mil'],
    [250000, 'doscientos cincuenta mil'],
    [1000000, 'un millón'],
    [1200000, 'un millón doscientos mil'],
    [2500050, 'dos millones quinientos mil cincuenta'],
    [21000000, 'veintiún millones'],
    [1000000000, 'mil millones'],
    [3100000000, 'tres mil cien millones'],
]);
