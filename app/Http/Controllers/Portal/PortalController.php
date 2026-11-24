<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AppointmentStatus;
use App\Enums\TreatmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Patients\DocumentController;
use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\ConsultationProcedure;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Tooth;
use App\Models\TreatmentItem;
use App\Services\Billing\BalanceCalculator;
use App\Services\Odontogram\OdontogramInsights;
use App\Services\Odontogram\OdontogramState;
use App\Support\SpanishNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Portal del paciente: solo lectura y siempre limitado al paciente de la
 * sesión. Nunca se toma un ID de paciente de la URL.
 */
class PortalController extends Controller
{
    public function home(Request $request, BalanceCalculator $balances, OdontogramInsights $insights): Response
    {
        $patient = $this->patient($request);
        $events = $patient->toothEvents()->get();

        return Inertia::render('portal/home', [
            'appointments' => $patient->appointments()
                ->with(['professional:id,name', 'type:id,name'])
                ->where('starts_at', '>=', now()->format('Y-m-d H:i:s'))
                ->whereIn('status', array_map(fn ($s) => $s->value, AppointmentStatus::occupying()))
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Appointment $a) => [
                    'id' => $a->id,
                    'starts_at' => $a->starts_at->format('Y-m-d H:i'),
                    'type' => $a->type->name,
                    'professional' => $a->professional->name,
                    'status_label' => $a->status->label(),
                ]),
            'balance' => $balances->forPatient($patient),
            'last_cleaning' => $insights->lastCleaning($events)?->toDateString(),
            'next_cleaning' => $insights->nextCleaning($events)?->toDateString(),
            'clinic' => ['phone' => config('clinic.phone'), 'address' => config('clinic.address')],
        ]);
    }

    public function odontogram(Request $request, OdontogramState $state): Response
    {
        $patient = $this->patient($request);

        return Inertia::render('portal/odontogram', [
            'chart' => $state->at($patient->toothEvents()->get(), CarbonImmutable::today()),
            'teeth' => Tooth::query()->orderBy('code')->get(['code', 'dentition', 'quadrant', 'position', 'arch', 'type']),
            'age' => $patient->age,
        ]);
    }

    /** Historial: fechas, profesional, motivo y procedimientos. Las notas clínicas internas no se muestran. */
    public function history(Request $request): Response
    {
        $patient = $this->patient($request);

        return Inertia::render('portal/history', [
            'consultations' => $patient->consultations()
                ->with(['professional:id,name', 'procedures.procedure:id,name'])
                ->latest('consulted_at')
                ->get()
                ->map(fn (Consultation $c) => [
                    'id' => $c->id,
                    'consulted_at' => $c->consulted_at->format('Y-m-d H:i'),
                    'professional' => $c->professional->name,
                    'reason' => $c->reason,
                    'procedures' => $c->procedures->map(fn (ConsultationProcedure $p) => $p->procedure->name.($p->tooth_code ? " (pieza {$p->tooth_code})" : ''))->values(),
                    'next_visit' => $c->next_visit,
                ]),
            'treatments' => TreatmentItem::query()
                ->with(['procedure:id,name', 'adjustments'])
                ->where('patient_id', $patient->id)
                ->where('status', TreatmentStatus::Completed)
                ->latest('completed_on')
                ->get()
                ->map(fn (TreatmentItem $i) => [
                    'id' => $i->id,
                    'procedure' => $i->procedure->name,
                    'tooth_code' => $i->tooth_code,
                    'completed_on' => $i->completed_on?->toDateString(),
                    'warranty_until' => $i->warrantyUntil()?->toDateString(),
                ]),
        ]);
    }

    public function documents(Request $request): Response
    {
        $patient = $this->patient($request);

        return Inertia::render('portal/documents', [
            'prescriptions' => $patient->prescriptions()->with('professional:id,name')->latest('issued_at')->get()->map(fn (Prescription $p) => [
                'id' => $p->id,
                'type' => $p->type->label(),
                'issued_at' => $p->issued_at->format('Y-m-d H:i'),
                'professional' => $p->professional->name,
                'url' => route('portal.prescriptions.pdf', $p),
            ]),
            'receipts' => $patient->payments()->whereNull('voided_at')->latest('paid_at')->get()->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at->format('Y-m-d H:i'),
                'method' => $p->method->label(),
                'url' => route('portal.receipts.pdf', $p),
            ]),
        ]);
    }

    public function prescriptionPdf(Request $request, Prescription $prescription): HttpResponse
    {
        $patient = $this->patient($request);
        abort_unless($prescription->patient_id === $patient->id, 404);

        return DocumentController::prescriptionResponse($patient, $prescription);
    }

    public function receiptPdf(Request $request, Payment $payment, BalanceCalculator $balances): HttpResponse
    {
        $patient = $this->patient($request);
        abort_unless($payment->patient_id === $patient->id && $payment->voided_at === null, 404);

        $payment->load(['receiver:id,name', 'plan:id,title']);

        $response = Pdf::loadView('pdf.receipt', [
            'payment' => $payment,
            'patient' => $patient,
            'amountInWords' => SpanishNumber::toWords($payment->amount),
            'balance' => $balances->forPatient($patient)['balance'],
            'clinic' => ['name' => config('app.name'), 'address' => config('clinic.address'), 'phone' => config('clinic.phone')],
        ])->setPaper('a5', 'landscape')->stream("recibo-{$payment->receipt_number}.pdf");

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function patient(Request $request): Patient
    {
        /** @var PatientAccount $account */
        $account = $request->user('patient');

        return $account->patient()->firstOrFail();
    }
}
