<?php

use App\Models\ToothEvent;
use App\Services\Odontogram\OdontogramInsights;
use App\Services\Odontogram\OdontogramState;
use Carbon\CarbonImmutable;

/** Evento en memoria (sin base de datos). */
function ev(int $id, ?int $tooth, ?string $surface, string $condition, string $status, string $detected, ?string $treated = null, array $extra = []): ToothEvent
{
    return (new ToothEvent)->forceFill(array_merge([
        'id' => $id,
        'patient_id' => 1,
        'tooth_code' => $tooth,
        'surface' => $surface,
        'condition' => $condition,
        'status' => $status,
        'detected_on' => $detected,
        'treated_on' => $treated,
        'professional_id' => 1,
        'is_void' => false,
        'corrects_event_id' => null,
    ], $extra));
}

function stateAt(array $events, string $date): array
{
    return (new OdontogramState)->at(collect($events), CarbonImmutable::parse($date));
}

it('no muestra nada antes de la fecha de detección', function () {
    $chart = stateAt([ev(1, 16, 'oclusal', 'caries', 'diagnosticado', '2026-03-01')], '2026-02-28');

    expect($chart)->toBe([]);
});

it('reconstruye un evento tratado: pendiente antes del tratamiento, tratado después', function () {
    $events = [ev(1, 16, 'oclusal', 'restauracion', 'tratado', '2026-01-10', '2026-03-05')];

    expect(stateAt($events, '2026-02-01')[16]['surfaces']['oclusal'])
        ->toMatchArray(['status' => 'diagnosticado', 'date' => '2026-01-10'])
        ->and(stateAt($events, '2026-03-05')[16]['surfaces']['oclusal'])
        ->toMatchArray(['status' => 'tratado', 'date' => '2026-03-05']);
});

it('en cada superficie gana el evento más reciente', function () {
    $events = [
        ev(1, 16, 'oclusal', 'caries', 'diagnosticado', '2026-01-10'),
        ev(2, 16, 'oclusal', 'restauracion', 'tratado', '2026-01-20', '2026-02-01'),
        ev(3, 16, 'mesial', 'caries', 'diagnosticado', '2026-01-25'),
    ];

    $chart = stateAt($events, '2026-06-01');

    expect($chart[16]['surfaces']['oclusal']['condition'])->toBe('restauracion')
        ->and($chart[16]['surfaces']['mesial']['condition'])->toBe('caries')
        ->and(stateAt($events, '2026-01-15')[16]['surfaces']['oclusal']['condition'])->toBe('caries');
});

it('conserva varias condiciones del diente completo a la vez', function () {
    $events = [
        ev(1, 36, null, 'endodoncia', 'tratado', '2025-05-01', '2025-05-10'),
        ev(2, 36, null, 'corona', 'tratado', '2025-06-01', '2025-06-20'),
    ];

    $conditions = array_column(stateAt($events, '2026-01-01')[36]['tooth'], 'condition');

    expect($conditions)->toBe(['endodoncia', 'corona']);
});

it('marca ausente tras una extracción realizada y presente con el implante posterior', function () {
    $events = [
        ev(1, 46, null, 'extraccion', 'tratado', '2025-01-05', '2025-01-10'),
        ev(2, 46, null, 'implante', 'tratado', '2025-06-01', '2025-09-01'),
    ];

    expect(stateAt($events, '2025-01-09')[46]['absent'])->toBeFalse()   // extracción indicada, aún no hecha
        ->and(stateAt($events, '2025-02-01')[46]['absent'])->toBeTrue()
        ->and(stateAt($events, '2025-10-01')[46]['absent'])->toBeFalse();
});

it('una corrección reemplaza al evento original en todas las fechas', function () {
    $events = [
        ev(1, 21, 'vestibular', 'caries', 'diagnosticado', '2026-01-10'),
        ev(2, 22, 'vestibular', 'caries', 'diagnosticado', '2026-01-10', null, ['corrects_event_id' => 1]),
    ];

    $chart = stateAt($events, '2026-02-01');

    expect($chart)->not->toHaveKey(21)
        ->and($chart[22]['surfaces']['vestibular']['id'])->toBe(2);
});

it('una anulación elimina el evento y no aparece ella misma', function () {
    $events = [
        ev(1, 13, 'distal', 'sellante', 'tratado', '2026-01-10', '2026-01-10'),
        ev(2, 13, 'distal', 'sellante', 'tratado', '2026-01-10', '2026-01-10', ['corrects_event_id' => 1, 'is_void' => true]),
    ];

    expect(stateAt($events, '2026-02-01'))->toBe([]);
});

it('las correcciones encadenadas dejan vigente solo la última', function () {
    $events = [
        ev(1, 11, 'oclusal', 'caries', 'diagnosticado', '2026-01-01'),
        ev(2, 11, 'mesial', 'caries', 'diagnosticado', '2026-01-01', null, ['corrects_event_id' => 1]),
        ev(3, 11, 'distal', 'caries', 'diagnosticado', '2026-01-01', null, ['corrects_event_id' => 2]),
    ];

    expect(array_keys(stateAt($events, '2026-02-01')[11]['surfaces']))->toBe(['distal']);
});

it('la limpieza no se dibuja en ningún diente', function () {
    expect(stateAt([ev(1, null, null, 'limpieza', 'tratado', '2026-01-01', '2026-01-01')], '2026-02-01'))->toBe([]);
});

describe('consultas derivadas', function () {
    it('calcula la última limpieza y la próxima a 6 meses', function () {
        config(['clinic.cleaning_interval_months' => 6]);
        $events = collect([
            ev(1, null, null, 'limpieza', 'tratado', '2025-03-10', '2025-03-10'),
            ev(2, null, null, 'limpieza', 'tratado', '2025-08-31', '2025-08-31'),
            ev(3, null, null, 'limpieza', 'diagnosticado', '2025-12-01'), // indicada, no realizada
            ev(4, null, null, 'limpieza', 'tratado', '2025-12-15', '2025-12-15', ['is_void' => true, 'corrects_event_id' => 99]),
        ]);
        $insights = new OdontogramInsights(new OdontogramState);

        expect($insights->lastCleaning($events)?->toDateString())->toBe('2025-08-31')
            ->and($insights->nextCleaning($events)?->toDateString())->toBe('2026-02-28'); // sin desbordar a marzo
    });

    it('sin limpiezas registradas no hay recomendación', function () {
        $insights = new OdontogramInsights(new OdontogramState);

        expect($insights->nextCleaning(collect()))->toBeNull();
    });

    it('obtiene la última intervención de cada diente', function () {
        $events = collect([
            ev(1, 16, 'oclusal', 'restauracion', 'tratado', '2025-01-01', '2025-01-05'),
            ev(2, 16, null, 'endodoncia', 'tratado', '2025-03-01', '2025-03-10'),
            ev(3, 16, 'mesial', 'caries', 'diagnosticado', '2025-04-01'),
            ev(4, 26, 'oclusal', 'sellante', 'tratado', '2025-02-01', '2025-02-01'),
        ]);

        $last = (new OdontogramInsights(new OdontogramState))->lastInterventionByTooth($events);

        expect($last[16])->toMatchArray(['date' => '2025-03-10', 'condition' => 'Endodoncia'])
            ->and($last[26])->toMatchArray(['date' => '2025-02-01', 'condition' => 'Sellante', 'surface' => 'Oclusal']);
    });
});
