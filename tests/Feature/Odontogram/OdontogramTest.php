<?php

use App\Exceptions\ImmutableRecordException;
use App\Models\Patient;
use App\Models\ToothEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->dentist = User::factory()->dentist()->create();
    $this->patient = Patient::factory()->create();
});

function eventPayload(array $overrides = []): array
{
    return array_merge([
        'tooth_code' => 16,
        'surface' => 'oclusal',
        'condition' => 'caries',
        'status' => 'diagnosticado',
        'detected_on' => now()->subDays(10)->toDateString(),
        'treated_on' => '',
        'notes' => 'Caries profunda',
    ], $overrides);
}

it('el catálogo FDI tiene 32 piezas permanentes y 20 temporales', function () {
    expect(DB::table('teeth')->where('dentition', 'permanente')->count())->toBe(32)
        ->and(DB::table('teeth')->where('dentition', 'temporal')->count())->toBe(20)
        ->and(DB::table('teeth')->whereIn('code', [19, 49, 56, 86, 10])->count())->toBe(0);
});

describe('registro', function () {
    it('el odontólogo registra un hallazgo con su profesional y fecha', function () {
        $this->actingAs($this->dentist)
            ->post(route('patients.odontogram.store', $this->patient), eventPayload())
            ->assertRedirect(route('patients.odontogram', $this->patient));

        $event = ToothEvent::firstOrFail();
        expect($event->tooth_code)->toBe(16)
            ->and($event->surface->value)->toBe('oclusal')
            ->and($event->professional_id)->toBe($this->dentist->id)
            ->and($event->notes)->toBe('Caries profunda')
            ->and(DB::table('tooth_events')->value('notes'))->not->toContain('Caries'); // cifrado
    });

    it('valida superficie, diente y fechas según la condición', function (array $payload, array $errors) {
        $this->actingAs($this->dentist)
            ->post(route('patients.odontogram.store', $this->patient), eventPayload($payload))
            ->assertSessionHasErrors($errors);

        expect(ToothEvent::count())->toBe(0);
    })->with([
        'caries sin superficie' => [['surface' => ''], ['surface' => 'Esta condición se registra sobre una superficie: selecciónela.']],
        'corona con superficie' => [['condition' => 'corona'], ['surface' => 'Esta condición corresponde al diente completo: no seleccione superficie.']],
        'limpieza con diente' => [['condition' => 'limpieza', 'surface' => ''], ['condition' => 'La limpieza se registra para toda la boca, sin diente.']],
        'tratado sin fecha' => [['status' => 'tratado'], ['treated_on' => 'Indique la fecha de tratamiento.']],
        'tratamiento antes de detección' => [['status' => 'tratado', 'treated_on' => now()->subDays(20)->toDateString()], ['treated_on' => 'La fecha de tratamiento no puede ser anterior a la de detección.']],
        'fecha futura' => [['detected_on' => now()->addDay()->toDateString()], ['detected_on' => 'La fecha de detección no puede ser futura.']],
        'diente inexistente' => [['tooth_code' => 19], ['tooth_code']],
    ]);

    it('registra una limpieza de toda la boca', function () {
        $date = now()->subDays(3)->toDateString();

        $this->actingAs($this->dentist)->post(route('patients.odontogram.store', $this->patient), [
            'condition' => 'limpieza', 'status' => 'tratado', 'detected_on' => $date, 'treated_on' => $date,
        ])->assertSessionHasNoErrors();

        expect(ToothEvent::firstOrFail()->tooth_code)->toBeNull();
    });
});

describe('permisos', function () {
    it('recepción no ve ni registra el odontograma', function () {
        $reception = User::factory()->reception()->create();

        $this->actingAs($reception)->get(route('patients.odontogram', $this->patient))->assertForbidden();
        $this->actingAs($reception)->post(route('patients.odontogram.store', $this->patient), eventPayload())->assertForbidden();
    });

    it('no se registra en pacientes archivados', function () {
        $this->patient->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('patients.odontogram.store', $this->patient), eventPayload())
            ->assertNotFound();
    });

    it('el administrador ve el odontograma de un paciente archivado en solo lectura', function () {
        $this->patient->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('patients.odontogram', $this->patient))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_record', false));
    });
});

describe('inmutabilidad y correcciones', function () {
    beforeEach(function () {
        $this->actingAs($this->dentist)->post(route('patients.odontogram.store', $this->patient), eventPayload());
        $this->event = ToothEvent::firstOrFail();
    });

    it('los eventos no se pueden modificar ni borrar', function () {
        expect(fn () => $this->event->update(['status' => 'tratado']))->toThrow(ImmutableRecordException::class)
            ->and(fn () => $this->event->delete())->toThrow(ImmutableRecordException::class)
            ->and(fn () => DB::table('tooth_events')->update(['status' => 'tratado']))->toThrow(QueryException::class)
            ->and(fn () => DB::table('tooth_events')->delete())->toThrow(QueryException::class);
    });

    it('una corrección crea un evento nuevo que referencia al original', function () {
        $this->actingAs($this->dentist)
            ->post(route('patients.odontogram.correct', [$this->patient, $this->event]), eventPayload([
                'tooth_code' => 17, 'reason' => 'Se cargó en el diente equivocado',
            ]))->assertRedirect();

        $correction = ToothEvent::latest('id')->firstOrFail();
        expect(ToothEvent::count())->toBe(2)
            ->and($correction->corrects_event_id)->toBe($this->event->id)
            ->and($correction->tooth_code)->toBe(17)
            ->and($correction->correction_reason)->toBe('Se cargó en el diente equivocado')
            ->and($this->event->fresh()->tooth_code)->toBe(16); // el original no cambió

        $this->actingAs($this->dentist)->get(route('patients.odontogram', $this->patient))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('chart.16')
                ->where('chart.17.surfaces.oclusal.id', $correction->id)
                ->where('events.1.is_superseded', true));
    });

    it('exige el motivo de la corrección', function () {
        $this->actingAs($this->dentist)
            ->post(route('patients.odontogram.correct', [$this->patient, $this->event]), eventPayload(['reason' => '']))
            ->assertSessionHasErrors(['reason' => 'El campo motivo de la corrección es obligatorio.']);
    });

    it('anula un registro cargado por error', function () {
        $this->actingAs($this->dentist)
            ->post(route('patients.odontogram.correct', [$this->patient, $this->event]), ['void' => '1', 'reason' => 'Duplicado'])
            ->assertSessionHasNoErrors();

        expect(ToothEvent::latest('id')->firstOrFail()->is_void)->toBeTrue();

        $this->actingAs($this->dentist)->get(route('patients.odontogram', $this->patient))
            ->assertInertia(fn (Assert $page) => $page->where('chart', []));
    });

    it('no permite corregir dos veces el mismo registro ni corregir una anulación', function () {
        $url = route('patients.odontogram.correct', [$this->patient, $this->event]);
        $this->actingAs($this->dentist)->post($url, ['void' => '1', 'reason' => 'Duplicado']);

        $this->actingAs($this->dentist)->post($url, ['void' => '1', 'reason' => 'Otra vez'])
            ->assertSessionHasErrors(['event' => 'Este registro ya fue corregido. Corrija el registro más reciente.']);

        $void = ToothEvent::latest('id')->firstOrFail();
        $this->actingAs($this->dentist)->post(route('patients.odontogram.correct', [$this->patient, $void]), ['void' => '1', 'reason' => 'Revertir'])
            ->assertSessionHasErrors(['event' => 'Una anulación no se puede corregir.']);
    });

    it('no permite corregir un evento de otro paciente', function () {
        $this->actingAs($this->dentist)
            ->post(route('patients.odontogram.correct', [Patient::factory()->create(), $this->event]), ['void' => '1', 'reason' => 'Intento indebido'])
            ->assertNotFound();
    });
});

describe('línea de tiempo y consultas', function () {
    beforeEach(function () {
        $this->actingAs($this->dentist)->post(route('patients.odontogram.store', $this->patient), eventPayload([
            'condition' => 'restauracion', 'status' => 'tratado',
            'detected_on' => now()->subDays(30)->toDateString(), 'treated_on' => now()->subDays(10)->toDateString(),
        ]));
    });

    it('reconstruye el odontograma en una fecha pasada', function () {
        $this->actingAs($this->dentist)
            ->get(route('patients.odontogram', ['patient' => $this->patient, 'fecha' => now()->subDays(20)->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_today', false)
                ->where('chart.16.surfaces.oclusal.status', 'diagnosticado'));

        $this->actingAs($this->dentist)
            ->get(route('patients.odontogram', $this->patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_today', true)
                ->where('chart.16.surfaces.oclusal.status', 'tratado')
                ->where('timeline', [now()->subDays(30)->toDateString(), now()->subDays(10)->toDateString()]));
    });

    it('rechaza fechas inválidas o futuras', function () {
        $this->actingAs($this->dentist)
            ->get(route('patients.odontogram', ['patient' => $this->patient, 'fecha' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['fecha' => 'No se puede ver el odontograma en una fecha futura.']);
    });

    it('informa la última limpieza, la próxima recomendada y los arreglos', function () {
        $cleaning = now()->subMonths(7)->toDateString();
        $this->actingAs($this->dentist)->post(route('patients.odontogram.store', $this->patient), [
            'condition' => 'limpieza', 'status' => 'tratado', 'detected_on' => $cleaning, 'treated_on' => $cleaning,
        ]);

        $this->actingAs($this->dentist)->get(route('patients.odontogram', $this->patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('insights.last_cleaning', $cleaning)
                ->where('insights.next_cleaning', now()->subMonths(7)->addMonthsNoOverflow(6)->toDateString())
                ->where('insights.cleaning_overdue', true)
                ->where('insights.last_by_tooth.16.condition', 'Restauración / obturación')
                ->has('insights.repairs', 2));
    });
});
