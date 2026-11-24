<?php

require_once __DIR__.'/../Billing/BillingHelpers.php';

use App\Models\Patient;
use App\Queries\ReportQueries;
use Carbon\CarbonImmutable;

beforeEach(fn () => billingSetup($this));

it('calcula ingresos por profesional y forma de pago, excluyendo anulados', function () {
    $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this))->assertSessionHasNoErrors();
    $this->actingAs($this->reception)->post(route('patients.payments.store', $this->patient), paymentPayload($this, [
        'amount' => '100.000', 'method' => 'transferencia', 'reference' => 'OP-1',
    ]))->assertSessionHasNoErrors();
    $this->actingAs($this->reception)->post(route('patients.payments.store', Patient::factory()->create()), paymentPayload($this, [
        'amount' => '50.000', 'treatment_plan_id' => '',
    ]))->assertSessionHasNoErrors();

    $queries = new ReportQueries(CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-01'));

    expect($queries->incomeByMethod())->toBe([
        ['method' => 'efectivo', 'label' => 'Efectivo', 'payments' => 2, 'total' => 350000],
        ['method' => 'transferencia', 'label' => 'Transferencia', 'payments' => 1, 'total' => 100000],
    ])->and(collect($queries->incomeByProfessional())->keyBy('professional')->map->total->all())->toBe([
        $this->dentist->name => 400000,
        'Sin asignar' => 50000,
    ]);

    $outside = new ReportQueries(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));
    expect($outside->incomeByMethod())->toBe([]);
});

it('lista pacientes sin control en los últimos meses', function () {
    $recent = Patient::factory()->create();
    item($this, 100000, 'completado', ['completed_on' => '2026-11-20']);
    $this->actingAs($this->dentist)->post(route('patients.consultations.store', $recent), [
        'consulted_at' => '2026-11-30T09:00', 'reason' => 'Control', 'subjective' => 'a', 'objective' => 'b', 'assessment' => 'c', 'plan' => 'd',
        'anesthesia_used' => '0', 'allergies_acknowledged' => '0', 'procedures' => [],
    ])->assertSessionHasNoErrors();

    $ids = array_column(ReportQueries::withoutControl(), 'id');

    expect($ids)->not->toContain($recent->id)->toContain($this->patient->id);
});

it('exporta CSV para Excel y neutraliza fórmulas', function () {
    $this->patient->update(['first_name' => '=HYPERLINK("http://x")']);

    $response = $this->actingAs($this->admin)->get(route('reports.csv', ['section' => 'sin-control']))->assertOk();
    $content = $response->streamedContent();

    expect($content)->toStartWith("\xEF\xBB\xBF")
        ->toContain('Paciente;Teléfono')
        ->toContain("'=HYPERLINK")
        ->and($response->headers->get('Content-Disposition'))->toContain('.csv');

    $this->actingAs($this->admin)->get(route('reports.csv', ['section' => 'otra']))->assertNotFound();
});

it('muestra la página y el PDF solo a quien tiene permiso', function () {
    $this->actingAs($this->admin)->get(route('reports.index', ['desde' => '2026-11-01', 'hasta' => '2026-12-01']))
        ->assertInertia(fn ($page) => $page->component('reports/index')->where('period.from', '2026-11-01')->has('no_show'));
    $this->actingAs($this->admin)->get(route('reports.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->admin)->get(route('reports.index', ['desde' => '2026-12-01', 'hasta' => '2026-11-01']))->assertSessionHasErrors('hasta');

    $this->actingAs($this->reception)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($this->reception)->get(route('reports.csv', ['section' => 'tratamientos']))->assertForbidden();
});
