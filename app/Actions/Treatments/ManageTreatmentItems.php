<?php

namespace App\Actions\Treatments;

use App\Enums\AdjustmentType;
use App\Enums\ToothSurface;
use App\Enums\TreatmentStatus;
use App\Models\Procedure;
use App\Models\TreatmentAdjustment;
use App\Models\TreatmentItem;
use App\Models\TreatmentPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageTreatmentItems
{
    /**
     * @param  array{procedure_id: int, description?: ?string, tooth_code?: ?int, surface?: ?string, price: int, warranty_months?: ?int}  $data
     */
    public function add(TreatmentPlan $plan, User $actor, array $data): TreatmentItem
    {
        $this->ensurePlanOpen($plan);
        $procedure = Procedure::query()->findOrFail($data['procedure_id']);

        return TreatmentItem::create([
            'treatment_plan_id' => $plan->id,
            'patient_id' => $plan->patient_id,
            'procedure_id' => $procedure->id,
            'description' => $data['description'] ?? null,
            'tooth_code' => $data['tooth_code'] ?? null,
            'surface' => isset($data['surface']) ? ToothSurface::from($data['surface']) : null,
            'price' => $data['price'],
            'warranty_months' => $data['warranty_months'] ?? $procedure->warranty_months,
            'status' => TreatmentStatus::Proposed,
            'professional_id' => $plan->professional_id,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Mientras no esté completado ni cancelado, el presupuesto se puede modificar.
     *
     * @param  array{description?: ?string, tooth_code?: ?int, surface?: ?string, price: int, warranty_months?: ?int}  $data
     */
    public function update(TreatmentItem $item, array $data): TreatmentItem
    {
        if ($item->status === TreatmentStatus::Completed) {
            throw ValidationException::withMessages(['price' => 'Un tratamiento completado no se edita: registre un ajuste.']);
        }

        if ($item->status === TreatmentStatus::Cancelled) {
            throw ValidationException::withMessages(['price' => 'Un tratamiento cancelado no se edita.']);
        }

        $item->update([
            'description' => $data['description'] ?? null,
            'tooth_code' => $data['tooth_code'] ?? null,
            'surface' => isset($data['surface']) ? ToothSurface::from($data['surface']) : null,
            'price' => $data['price'],
            'warranty_months' => $data['warranty_months'] ?? $item->warranty_months,
        ]);

        return $item;
    }

    public function changeStatus(TreatmentItem $item, TreatmentStatus $target, ?string $reason = null, ?CarbonImmutable $completedOn = null, ?int $consultationId = null): TreatmentItem
    {
        if (! $item->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => sprintf('No se puede pasar un tratamiento de "%s" a "%s".', $item->status->label(), $target->label()),
            ]);
        }

        if ($target === TreatmentStatus::Cancelled && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Indique el motivo de la cancelación.']);
        }

        $completedOn ??= CarbonImmutable::today();

        if ($target === TreatmentStatus::Completed && $completedOn->isFuture()) {
            throw ValidationException::withMessages(['completed_on' => 'La fecha de realización no puede ser futura.']);
        }

        $item->update(array_filter([
            'status' => $target,
            'accepted_at' => $target === TreatmentStatus::Accepted ? now() : null,
            'started_at' => $target === TreatmentStatus::InProgress ? now() : null,
            'completed_on' => $target === TreatmentStatus::Completed ? $completedOn : null,
            'consultation_id' => $target === TreatmentStatus::Completed ? $consultationId : null,
            'cancelled_at' => $target === TreatmentStatus::Cancelled ? now() : null,
            'cancellation_reason' => $target === TreatmentStatus::Cancelled ? $reason : null,
        ], fn ($v) => $v !== null));

        return $item;
    }

    /** Regla 3: la única forma de corregir un tratamiento completado. */
    public function adjust(TreatmentItem $item, User $actor, AdjustmentType $type, ?string $newValue, string $reason): TreatmentAdjustment
    {
        if ($item->status !== TreatmentStatus::Completed) {
            throw ValidationException::withMessages(['type' => 'Los ajustes son para tratamientos completados. Este todavía se puede editar.']);
        }

        $old = match ($type) {
            AdjustmentType::Price => (string) $item->effectivePrice(),
            AdjustmentType::Warranty => (string) $item->effectiveWarrantyMonths(),
            AdjustmentType::Note => null,
        };

        return DB::transaction(fn () => TreatmentAdjustment::create([
            'treatment_item_id' => $item->id,
            'type' => $type,
            'old_value' => $old,
            'new_value' => $newValue,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]));
    }

    private function ensurePlanOpen(TreatmentPlan $plan): void
    {
        if ($plan->closed_at !== null) {
            throw ValidationException::withMessages(['procedure_id' => 'El plan está cerrado. Cree un plan nuevo.']);
        }
    }
}
