<?php

namespace App\Services\Billing;

use App\Models\Installment;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use Carbon\CarbonImmutable;

/**
 * Estado de las cuotas de un plan, calculado: los pagos válidos del plan se
 * imputan a las cuotas en orden (primero la más antigua).
 */
class InstallmentStatus
{
    /**
     * @return list<array{id: int, number: int, due_date: string, amount: int, paid: int, pending: int, status: string, status_label: string}>
     */
    public function for(TreatmentPlan $plan, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $available = (int) Payment::query()->where('treatment_plan_id', $plan->id)->valid()->sum('amount');
        $rows = [];

        foreach ($plan->installments()->get() as $installment) {
            /** @var Installment $installment */
            $paid = min($available, $installment->amount);
            $available -= $paid;
            $pending = $installment->amount - $paid;

            [$status, $label] = match (true) {
                $pending === 0 => ['pagada', 'Pagada'],
                $installment->due_date->lt($today) => ['vencida', 'Vencida'],
                $paid > 0 => ['parcial', 'Pago parcial'],
                default => ['pendiente', 'Pendiente'],
            };

            $rows[] = [
                'id' => $installment->id,
                'number' => $installment->number,
                'due_date' => $installment->due_date->toDateString(),
                'amount' => $installment->amount,
                'paid' => $paid,
                'pending' => $pending,
                'status' => $status,
                'status_label' => $label,
            ];
        }

        return $rows;
    }
}
