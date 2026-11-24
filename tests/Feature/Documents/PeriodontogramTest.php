<?php

require_once __DIR__.'/../Billing/BillingHelpers.php';

use App\Exceptions\ImmutableRecordException;
use App\Models\PeriodontalMeasurement;
use App\Models\PeriodontalRecord;
use App\Services\Periodontal\PeriodontalSummary;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => billingSetup($this));

/** @return array<string, mixed> */
function perioTooth(array $depth, array $bleeding = [false, false, false, false, false, false], bool $missing = false): array
{
    return [
        'is_missing' => $missing,
        'probing_depth' => $depth,
        'recession' => [0, 0, 0, 0, 0, 0],
        'bleeding' => $bleeding,
        'plaque' => [true, false, false, false, false, false],
        'mobility' => 1,
    ];
}

function storePerio(object $test, array $teeth, string $date = '2026-11-30'): TestResponse
{
    return $test->actingAs($test->dentist)->post(route('patients.periodontogram.store', $test->patient), [
        'recorded_on' => $date,
        'notes' => 'Bolsas en molares',
        'teeth' => $teeth,
    ]);
}

it('registra el examen, ignora piezas vacías y calcula los indicadores', function () {
    $empty = perioTooth([null, null, null, null, null, null]);
    $empty['mobility'] = null;

    storePerio($this, [
        16 => perioTooth([3, 4, 6, 2, 2, 3], [true, true, false, false, false, false]),
        11 => perioTooth([2, 2, 2, 2, 2, 2]),
        21 => $empty,
        46 => perioTooth([null, null, null, null, null, null], missing: true),
    ])->assertSessionHasNoErrors();

    $record = PeriodontalRecord::firstOrFail();
    expect($record->measurements()->count())->toBe(3)
        ->and(DB::table('periodontal_records')->value('notes'))->not->toContain('Bolsas');

    $summary = app(PeriodontalSummary::class)->for($record->load('measurements'));
    expect($summary['teeth'])->toBe(2)
        ->and($summary['sites'])->toBe(12)
        ->and($summary['bleeding_pct'])->toEqual(16.7)
        ->and($summary['plaque_pct'])->toEqual(16.7)
        ->and($summary['mean_depth'])->toEqual(2.7)
        ->and($summary['sites_4_plus'])->toBe(2)
        ->and($summary['sites_6_plus'])->toBe(1);
});

it('valida rangos, piezas y que haya al menos un diente', function () {
    storePerio($this, [16 => perioTooth([3, 3, 3, 3, 3, 20])])->assertSessionHasErrors('teeth.16.probing_depth.5');
    storePerio($this, [55 => perioTooth([3, 3, 3, 3, 3, 3])])->assertSessionHasErrors('teeth');
    storePerio($this, [16 => perioTooth([3, 3, 3])])->assertSessionHasErrors('teeth.16.probing_depth');
    storePerio($this, [16 => perioTooth([3, 3, 3, 3, 3, 3])], '2026-12-05')->assertSessionHasErrors('recorded_on');

    expect(PeriodontalRecord::count())->toBe(0);
});

it('los exámenes son inmutables y recepción no puede registrarlos', function () {
    storePerio($this, [16 => perioTooth([3, 3, 3, 3, 3, 3])]);
    $record = PeriodontalRecord::firstOrFail();

    expect(fn () => $record->update(['notes' => 'x']))->toThrow(ImmutableRecordException::class)
        ->and(fn () => PeriodontalMeasurement::firstOrFail()->delete())->toThrow(ImmutableRecordException::class)
        ->and(fn () => DB::table('periodontal_measurements')->update(['mobility' => 3]))->toThrow(QueryException::class);

    $this->actingAs($this->reception)->get(route('patients.periodontogram', $this->patient))->assertForbidden();
});

it('muestra la evolución y el examen elegido', function () {
    storePerio($this, [16 => perioTooth([5, 5, 5, 5, 5, 5])], '2026-06-01');
    storePerio($this, [16 => perioTooth([3, 3, 3, 3, 3, 3])], '2026-11-30');
    $first = PeriodontalRecord::orderBy('id')->firstOrFail();

    $this->actingAs($this->dentist)->get(route('patients.periodontogram', [$this->patient, 'registro' => $first->id]))
        ->assertInertia(fn ($page) => $page->component('patients/periodontogram')
            ->has('records', 2)
            ->where('records.0.summary.mean_depth', 5)
            ->where('selected.id', $first->id)
            ->where('selected.measurements.16.probing_depth.0', 5));
});
