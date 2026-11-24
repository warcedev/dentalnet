<?php

namespace App\Actions\Billing;

use App\Enums\PaymentMethod;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterPayment
{
    public function handle(
        Patient $patient,
        User $actor,
        int $amount,
        PaymentMethod $method,
        CarbonImmutable $paidAt,
        ?TreatmentPlan $plan = null,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        if ($plan !== null && $plan->patient_id !== $patient->id) {
            throw ValidationException::withMessages(['treatment_plan_id' => 'El plan no corresponde al paciente.']);
        }

        // El número de recibo es correlativo y único: se asigna bajo candado.
        return Cache::lock('receipt-number', 10)->block(10, fn () => DB::transaction(fn () => Payment::create([
            'patient_id' => $patient->id,
            'treatment_plan_id' => $plan?->id,
            'receipt_number' => (int) Payment::query()->max('receipt_number') + 1,
            'amount' => $amount,
            'method' => $method,
            'reference' => $reference,
            'paid_at' => $paidAt,
            'notes' => $notes,
            'received_by' => $actor->id,
        ])));
    }

    public function void(Payment $payment, User $actor, string $reason): Payment
    {
        if ($payment->voided_at !== null) {
            throw ValidationException::withMessages(['reason' => 'El pago ya estaba anulado.']);
        }

        // Los campos de anulación no son asignables en bloque: este es el único camino.
        $payment->forceFill(['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason])->save();

        return $payment;
    }
}
