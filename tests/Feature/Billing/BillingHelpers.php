<?php

use App\Models\Patient;
use App\Models\Procedure;
use App\Models\TreatmentItem;
use App\Models\TreatmentPlan;
use App\Models\User;

function billingSetup(object $test): void
{
    $test->travelTo('2026-12-01 10:00:00');
    $test->admin = User::factory()->admin()->create();
    $test->dentist = User::factory()->dentist()->create();
    $test->reception = User::factory()->reception()->create();
    $test->patient = Patient::factory()->create();
    $test->plan = TreatmentPlan::create(['patient_id' => $test->patient->id, 'title' => 'Plan', 'professional_id' => $test->dentist->id]);
}

function item(object $test, int $price, string $status = 'aceptado', array $extra = []): TreatmentItem
{
    return TreatmentItem::create(array_merge([
        'treatment_plan_id' => $test->plan->id,
        'patient_id' => $test->patient->id,
        'procedure_id' => Procedure::where('name', 'Obturación con resina')->value('id'),
        'price' => $price,
        'warranty_months' => 12,
        'status' => $status,
        'completed_on' => $status === 'completado' ? '2026-11-20' : null,
    ], $extra));
}

/** @return array<string, mixed> */
function paymentPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'amount' => '300.000',
        'method' => 'efectivo',
        'treatment_plan_id' => $test->plan->id,
        'paid_on' => '2026-12-01',
    ], $overrides);
}
