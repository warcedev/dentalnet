<?php

namespace App\Actions\Billing;

use App\Models\TreatmentPlan;
use App\Services\Billing\BalanceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Divide el saldo actual del plan en cuotas mensuales iguales (en guaraníes
 * enteros; la diferencia por redondeo va en la última). Reemplaza el
 * cronograma anterior: las cuotas son un plan de pagos, no pagos.
 */
class ScheduleInstallments
{
    public function __construct(private readonly BalanceCalculator $balances) {}

    public function handle(TreatmentPlan $plan, int $count, CarbonImmutable $firstDue): void
    {
        $balance = $this->balances->forPlan($plan)['balance'];

        if ($balance <= 0) {
            throw ValidationException::withMessages(['count' => 'El plan no tiene saldo pendiente para financiar.']);
        }

        $base = intdiv($balance, $count);
        $remainder = $balance - $base * $count;

        DB::transaction(function () use ($plan, $count, $firstDue, $base, $remainder) {
            $plan->installments()->get()->each->delete();

            for ($n = 1; $n <= $count; $n++) {
                $plan->installments()->create([
                    'number' => $n,
                    'due_date' => $firstDue->addMonthsNoOverflow($n - 1),
                    'amount' => $base + ($n === $count ? $remainder : 0),
                ]);
            }
        });
    }
}
