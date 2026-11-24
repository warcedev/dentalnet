<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Billing\ScheduleInstallments;
use App\Actions\Treatments\ManageTreatmentItems;
use App\Enums\AdjustmentType;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\TreatmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Presenters\PatientPresenter;
use App\Http\Requests\Treatments\TreatmentItemRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Procedure;
use App\Models\TreatmentAdjustment;
use App\Models\TreatmentItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\Billing\BalanceCalculator;
use App\Services\Billing\InstallmentStatus;
use App\Support\EnumOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Planes de tratamiento, presupuesto, cuotas y pagos de un paciente.
 */
class TreatmentController extends Controller
{
    public function __construct(
        private readonly PatientPresenter $presenter,
        private readonly BalanceCalculator $balances,
        private readonly InstallmentStatus $installments,
    ) {}

    public function index(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewAny', TreatmentPlan::class);

        $user = $request->user('web');
        $plans = $patient->treatmentPlans()
            ->with(['professional:id,name', 'items' => fn ($q) => $q->with(['procedure:id,name', 'adjustments.author:id,name'])->orderBy('id')])
            ->latest('id')
            ->get();

        $payments = $user->can(Permission::PaymentsView->value)
            ? $patient->payments()->with(['receiver:id,name', 'voider:id,name', 'plan:id,title'])->latest('paid_at')->latest('id')->get()
            : collect();

        return Inertia::render('patients/treatments', [
            'header' => $this->presenter->header($patient, $user),
            'summary' => $user->can(Permission::PaymentsView->value) ? $this->balances->forPatient($patient) : null,
            'plans' => $plans->map(fn (TreatmentPlan $plan) => [
                'id' => $plan->id,
                'title' => $plan->title,
                'professional' => $plan->professional->name,
                'notes' => $plan->notes,
                'closed' => $plan->closed_at !== null,
                'created_at' => $plan->created_at?->toIso8601String(),
                'summary' => $this->balances->forPlan($plan),
                'installments' => $this->installments->for($plan),
                'items' => $plan->items->map(fn (TreatmentItem $i) => $this->presentItem($i)),
            ]),
            'payments' => $payments->map(fn (Payment $p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'amount' => $p->amount,
                'method' => $p->method->label(),
                'reference' => $p->reference,
                'paid_at' => $p->paid_at->format('Y-m-d H:i'),
                'plan' => $p->plan?->title,
                'received_by' => $p->receiver->name,
                'voided' => $p->voided_at !== null,
                'void_reason' => $p->void_reason,
                'voided_by' => $p->voider?->name,
                'receipt_url' => route('patients.payments.receipt', [$patient, $p]),
            ]),
            'expiring_warranties' => $plans->flatMap->items
                ->filter(fn (TreatmentItem $i) => ($until = $i->warrantyUntil()) !== null && $until->between(CarbonImmutable::today(), CarbonImmutable::today()->addDays(60)))
                ->map(fn (TreatmentItem $i) => ['id' => $i->id, 'procedure' => $i->procedure->name, 'tooth_code' => $i->tooth_code, 'until' => $i->warrantyUntil()?->toDateString()])
                ->values(),
            'options' => [
                'procedures' => Procedure::query()->active()->orderBy('name')->get(['id', 'name', 'default_price', 'warranty_months']),
                'professionals' => User::role(Role::Dentist->value)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'methods' => EnumOptions::for(PaymentMethod::class),
                'adjustment_types' => EnumOptions::for(AdjustmentType::class),
            ],
            'can' => [
                'manage' => $user->can('create', TreatmentPlan::class) && ! $patient->trashed(),
                'register_payment' => $user->can('create', Payment::class) && ! $patient->trashed(),
                'void_payment' => $user->can(Permission::PaymentsVoid->value),
                'view_payments' => $user->can(Permission::PaymentsView->value),
            ],
        ]);
    }

    public function storePlan(Request $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('create', TreatmentPlan::class);
        abort_if($patient->trashed(), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'professional_id' => ['required', 'integer', Rule::in(User::role(Role::Dentist->value)->where('is_active', true)->pluck('id')->all())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['professional_id.in' => 'Seleccione un odontólogo activo.'], ['title' => 'título', 'professional_id' => 'profesional', 'notes' => 'notas']);

        $patient->treatmentPlans()->create([...$data, 'created_by' => $request->user('web')->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan de tratamiento creado.']);

        return back();
    }

    public function closePlan(Patient $patient, TreatmentPlan $treatmentPlan): RedirectResponse
    {
        Gate::authorize('update', $treatmentPlan);

        $treatmentPlan->update(['closed_at' => $treatmentPlan->closed_at === null ? now() : null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $treatmentPlan->closed_at ? 'Plan cerrado.' : 'Plan reabierto.']);

        return back();
    }

    public function storeItem(TreatmentItemRequest $request, Patient $patient, TreatmentPlan $treatmentPlan, ManageTreatmentItems $action): RedirectResponse
    {
        $action->add($treatmentPlan, $request->user('web'), $request->itemData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tratamiento agregado al presupuesto.']);

        return back();
    }

    public function updateItem(TreatmentItemRequest $request, Patient $patient, TreatmentPlan $treatmentPlan, TreatmentItem $treatmentItem, ManageTreatmentItems $action): RedirectResponse
    {
        $action->update($treatmentItem, $request->itemData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tratamiento actualizado.']);

        return back();
    }

    public function itemStatus(Request $request, Patient $patient, TreatmentPlan $treatmentPlan, TreatmentItem $treatmentItem, ManageTreatmentItems $action): RedirectResponse
    {
        Gate::authorize('update', $treatmentPlan);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TreatmentStatus::class)],
            'reason' => ['nullable', 'string', 'max:300'],
            'completed_on' => ['nullable', 'date_format:Y-m-d'],
        ], attributes: ['status' => 'estado', 'reason' => 'motivo', 'completed_on' => 'fecha de realización']);

        $target = TreatmentStatus::from($data['status']);
        $completedOn = isset($data['completed_on']) ? (CarbonImmutable::createFromFormat('!Y-m-d', $data['completed_on']) ?: null) : null;
        $action->changeStatus($treatmentItem, $target, $data['reason'] ?? null, $completedOn);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tratamiento: '.mb_strtolower($target->label()).'.']);

        return back();
    }

    public function adjust(Request $request, Patient $patient, TreatmentPlan $treatmentPlan, TreatmentItem $treatmentItem, ManageTreatmentItems $action): RedirectResponse
    {
        Gate::authorize('update', $treatmentPlan);

        $request->merge(['new_value' => is_string($request->input('new_value')) && $request->input('type') === 'precio'
            ? preg_replace('/\D/', '', $request->input('new_value')) : $request->input('new_value')]);

        $data = $request->validate([
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            'new_value' => ['nullable', 'required_unless:type,nota', 'integer', 'min:0', 'max:999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], ['new_value.required_unless' => 'Indique el nuevo valor.'], ['type' => 'tipo de ajuste', 'new_value' => 'nuevo valor', 'reason' => 'motivo']);

        $type = AdjustmentType::from($data['type']);
        $action->adjust($treatmentItem, $request->user('web'), $type, isset($data['new_value']) ? (string) $data['new_value'] : null, $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $type->label().' registrado. El tratamiento original no se modificó.']);

        return back();
    }

    public function storeInstallments(Request $request, Patient $patient, TreatmentPlan $treatmentPlan, ScheduleInstallments $action): RedirectResponse
    {
        Gate::authorize('update', $treatmentPlan);

        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:36'],
            'first_due' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], attributes: ['count' => 'cantidad de cuotas', 'first_due' => 'primer vencimiento']);

        $action->handle($treatmentPlan, (int) $data['count'], CarbonImmutable::createFromFormat('!Y-m-d', $data['first_due']) ?: CarbonImmutable::today());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se generaron {$data['count']} cuotas."]);

        return back();
    }

    /** @return array<string, mixed> */
    private function presentItem(TreatmentItem $item): array
    {
        return [
            'id' => $item->id,
            'procedure' => $item->procedure->name,
            'description' => $item->description,
            'tooth_code' => $item->tooth_code,
            'surface' => $item->surface?->value,
            'surface_label' => $item->surface?->labelFor($item->tooth_code),
            'price' => $item->price,
            'effective_price' => $item->effectivePrice(),
            'warranty_months' => $item->effectiveWarrantyMonths(),
            'warranty_until' => $item->warrantyUntil()?->toDateString(),
            'warranty_expired' => ($until = $item->warrantyUntil()) !== null && $until->isPast(),
            'status' => $item->status->value,
            'status_label' => $item->status->label(),
            'transitions' => array_map(fn (TreatmentStatus $s) => ['value' => $s->value, 'label' => $s->actionLabel()], $item->status->allowedTransitions()),
            'completed_on' => $item->completed_on?->toDateString(),
            'cancellation_reason' => $item->cancellation_reason,
            'editable' => ! in_array($item->status, [TreatmentStatus::Completed, TreatmentStatus::Cancelled], true),
            'adjustments' => $item->adjustments->sortBy('id')->values()->map(fn (TreatmentAdjustment $a) => [
                'id' => $a->id,
                'type' => $a->type->label(),
                'old_value' => $a->old_value,
                'new_value' => $a->new_value,
                'is_money' => $a->type === AdjustmentType::Price,
                'reason' => $a->reason,
                'author' => $a->author->name,
                'created_at' => $a->created_at->toIso8601String(),
            ]),
        ];
    }
}
