<?php

use App\Models\Patient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Patient::factory()->create(['first_name' => 'María José', 'last_name' => 'Benítez Ortiz', 'document_number' => '4567890', 'phone' => '0981 123 456']);
    Patient::factory()->create(['first_name' => 'Luis', 'last_name' => 'Giménez Duarte', 'document_number' => '1234567', 'phone' => '0991 555 777']);
    Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Núñez', 'document_number' => '7654321', 'phone' => null]);
});

function searchNames(string $term): array
{
    return Patient::query()->search($term)->orderBy('id')->get()->map->full_name->all();
}

it('busca por nombre sin importar acentos ni mayúsculas', function () {
    expect(searchNames('BENITEZ'))->toBe(['María José Benítez Ortiz'])
        ->and(searchNames('nunez'))->toBe(['Ana Núñez'])
        ->and(searchNames('maría benítez'))->toBe(['María José Benítez Ortiz']);
});

it('busca por documento y por teléfono', function () {
    expect(searchNames('1234567'))->toBe(['Luis Giménez Duarte'])
        ->and(searchNames('76543'))->toBe(['Ana Núñez'])
        ->and(searchNames('0981 123'))->toBe(['María José Benítez Ortiz'])
        ->and(searchNames('555777'))->toBe(['Luis Giménez Duarte']);
});

it('trata los comodines como texto literal', function () {
    expect(searchNames('%'))->toBe([])
        ->and(searchNames('_'))->toBe([]);
});

it('la pantalla de pacientes aplica la búsqueda', function () {
    $this->actingAs(User::factory()->reception()->create())
        ->get(route('patients.index', ['buscar' => 'gimenez']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('patients.data', 1)
            ->where('patients.data.0.full_name', 'Giménez Duarte, Luis'));
});
