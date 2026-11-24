<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Billing\RegisterPayment;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Treatments\PaymentRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Services\Billing\BalanceCalculator;
use App\Support\SpanishNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function store(PaymentRequest $request, Patient $patient, RegisterPayment $action): RedirectResponse
    {
        abort_if($patient->trashed(), 404);

        $paidOn = CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->input('paid_on')) ?: CarbonImmutable::today();
        // Si es de hoy, se guarda la hora real; si es de otro día, a las 12:00.
        $paidAt = $paidOn->isToday() ? CarbonImmutable::now() : $paidOn->setTime(12, 0);

        $payment = $action->handle(
            $patient,
            $request->user('web'),
            $request->integer('amount'),
            PaymentMethod::from((string) $request->input('method')),
            $paidAt,
            $request->filled('treatment_plan_id') ? TreatmentPlan::query()->find($request->integer('treatment_plan_id')) : null,
            $request->filled('reference') ? $request->string('reference')->trim()->value() : null,
            $request->filled('notes') ? $request->string('notes')->trim()->value() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pago registrado. Recibo N.º {$payment->receipt_number}."]);

        return back();
    }

    public function void(Request $request, Patient $patient, Payment $payment, RegisterPayment $action): RedirectResponse
    {
        Gate::authorize('update', $payment);

        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:300']], attributes: ['reason' => 'motivo']);
        $action->void($payment, $request->user('web'), $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Recibo N.º {$payment->receipt_number} anulado. El saldo se recalculó."]);

        return back();
    }

    public function receipt(Patient $patient, Payment $payment, BalanceCalculator $balances): Response
    {
        Gate::authorize('view', $payment);

        $payment->load(['receiver:id,name', 'plan:id,title']);

        $pdf = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'patient' => $patient,
            'amountInWords' => SpanishNumber::toWords($payment->amount),
            'balance' => $balances->forPatient($patient)['balance'],
            'clinic' => [
                'name' => config('app.name'),
                'address' => config('clinic.address'),
                'phone' => config('clinic.phone'),
            ],
        ])->setPaper('a5', 'landscape');

        $response = $pdf->stream("recibo-{$payment->receipt_number}.pdf");
        // Contiene datos del paciente: que el navegador no lo guarde en caché.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
