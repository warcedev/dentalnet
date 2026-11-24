<?php

namespace App\Services\Billing;

use App\Enums\TreatmentStatus;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentItem;
use App\Models\TreatmentPlan;
use Illuminate\Support\Collection;

/**
 * Regla de negocio 4: el saldo se calcula siempre, nunca se guarda.
 *
 * Saldo = Σ precio vigente de los ítems aceptados, en curso o completados
 *       − Σ pagos no anulados.
 * Un saldo negativo es saldo a favor del paciente.
 */
class BalanceCalculator
{
    /** @return array{proposed: int, billed: int, paid: int, balance: int} */
    public function forPatient(Patient $patient): array
    {
        $items = TreatmentItem::query()->with('adjustments')->where('patient_id', $patient->id)->get();
        $paid = (int) Payment::query()->where('patient_id', $patient->id)->valid()->sum('amount');

        return $this->summary($items, $paid);
    }

    /** @return array{proposed: int, billed: int, paid: int, balance: int} */
    public function forPlan(TreatmentPlan $plan): array
    {
        $items = $plan->relationLoaded('items') ? $plan->items : $plan->items()->with('adjustments')->get();
        $paid = (int) Payment::query()->where('treatment_plan_id', $plan->id)->valid()->sum('amount');

        return $this->summary($items, $paid);
    }

    /**
     * @param  Collection<int, TreatmentItem>  $items
     * @return array{proposed: int, billed: int, paid: int, balance: int}
     */
    private function summary(Collection $items, int $paid): array
    {
        $billed = $items->filter(fn (TreatmentItem $i) => $i->status->isBillable())->sum(fn (TreatmentItem $i) => $i->effectivePrice());
        $proposed = $items->filter(fn (TreatmentItem $i) => $i->status === TreatmentStatus::Proposed)->sum(fn (TreatmentItem $i) => $i->effectivePrice());

        return [
            'proposed' => (int) $proposed,
            'billed' => (int) $billed,
            'paid' => $paid,
            'balance' => (int) $billed - $paid,
        ];
    }
}
