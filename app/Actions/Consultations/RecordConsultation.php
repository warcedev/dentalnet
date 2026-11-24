<?php

namespace App\Actions\Consultations;

use App\Actions\Treatments\ManageTreatmentItems;
use App\Data\ConsultationData;
use App\Enums\ToothConditionScope;
use App\Enums\ToothEventStatus;
use App\Enums\ToothSurface;
use App\Enums\TreatmentStatus;
use App\Models\Allergy;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ToothEvent;
use App\Models\TreatmentItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una consulta con sus procedimientos. Además:
 * - Completa los ítems de tratamiento vinculados.
 * - Registra en el odontograma los procedimientos que tienen condición asociada.
 * - Regla 6: si hubo anestesia y el paciente tiene alergias, exige la confirmación.
 */
class RecordConsultation
{
    public function __construct(private readonly ManageTreatmentItems $treatments) {}

    public function handle(Patient $patient, User $professional, ConsultationData $data): Consultation
    {
        $this->ensureAllergiesAcknowledged($patient, $data);

        return DB::transaction(function () use ($patient, $professional, $data) {
            $consultation = Consultation::create([
                'patient_id' => $patient->id,
                'professional_id' => $professional->id,
                'appointment_id' => $data->appointmentId,
                'consulted_at' => $data->consultedAt,
                'reason' => $data->reason,
                'subjective' => $data->subjective,
                'objective' => $data->objective,
                'assessment' => $data->assessment,
                'plan' => $data->plan,
                'materials' => $data->materials,
                'observations' => $data->observations,
                'next_visit' => $data->nextVisit,
                'next_visit_on' => $data->nextVisitOn,
                'anesthesia_used' => $data->anesthesiaUsed,
                'allergies_acknowledged_at' => $data->allergiesAcknowledged ? now() : null,
            ]);

            foreach ($data->procedures as $index => $row) {
                $this->recordProcedure($consultation, $patient, $professional, $row, $index);
            }

            return $consultation;
        });
    }

    /**
     * @param  array{procedure_id: int, treatment_item_id: ?int, tooth_code: ?int, surface: ?string, notes: ?string}  $row
     */
    private function recordProcedure(Consultation $consultation, Patient $patient, User $professional, array $row, int $index): void
    {
        $item = null;

        if ($row['treatment_item_id'] !== null) {
            $item = TreatmentItem::query()->where('patient_id', $patient->id)->find($row['treatment_item_id']);

            if ($item === null || ! in_array($item->status, [TreatmentStatus::Accepted, TreatmentStatus::InProgress], true)) {
                throw ValidationException::withMessages([
                    "procedures.{$index}.treatment_item_id" => 'El tratamiento debe estar aceptado o en curso para completarlo.',
                ]);
            }

            // Lo vinculado manda: procedimiento y pieza del plan.
            $row['procedure_id'] = $item->procedure_id;
            $row['tooth_code'] ??= $item->tooth_code;
            $row['surface'] ??= $item->surface?->value;
        }

        $procedure = Procedure::query()->findOrFail($row['procedure_id']);

        $consultation->procedures()->create([
            'procedure_id' => $procedure->id,
            'treatment_item_id' => $item?->id,
            'tooth_code' => $row['tooth_code'],
            'surface' => $row['surface'],
            'notes' => $row['notes'],
        ]);

        if ($item !== null) {
            $this->treatments->changeStatus($item, TreatmentStatus::Completed, completedOn: $consultation->consulted_at->startOfDay(), consultationId: $consultation->id);
        }

        $this->recordInOdontogram($consultation, $professional, $procedure, $row, $index);
    }

    /**
     * @param  array{procedure_id: int, treatment_item_id: ?int, tooth_code: ?int, surface: ?string, notes: ?string}  $row
     */
    private function recordInOdontogram(Consultation $consultation, User $professional, Procedure $procedure, array $row, int $index): void
    {
        $condition = $procedure->tooth_condition;

        if ($condition === null) {
            return;
        }

        $scope = $condition->scope();
        $tooth = $scope === ToothConditionScope::Mouth ? null : $row['tooth_code'];
        $surface = $scope === ToothConditionScope::Surface ? $row['surface'] : null;

        if ($scope !== ToothConditionScope::Mouth && $tooth === null) {
            throw ValidationException::withMessages(["procedures.{$index}.tooth_code" => "Indique el diente para «{$procedure->name}»."]);
        }

        if ($scope === ToothConditionScope::Surface && $surface === null) {
            throw ValidationException::withMessages(["procedures.{$index}.surface" => "Indique la superficie para «{$procedure->name}»."]);
        }

        $date = $consultation->consulted_at->startOfDay();

        ToothEvent::create([
            'patient_id' => $consultation->patient_id,
            'tooth_code' => $tooth,
            'surface' => $surface !== null ? ToothSurface::from($surface) : null,
            'condition' => $condition,
            'status' => ToothEventStatus::Treated,
            'detected_on' => $date,
            'treated_on' => $date,
            'professional_id' => $professional->id,
            'consultation_id' => $consultation->id,
            'notes' => $row['notes'],
        ]);
    }

    /** Regla de negocio 6. */
    private function ensureAllergiesAcknowledged(Patient $patient, ConsultationData $data): void
    {
        if ($data->allergiesAcknowledged) {
            return;
        }

        // Anestesia marcada, o algún procedimiento que la requiere (también los vinculados a un plan).
        $procedureIds = array_column($data->procedures, 'procedure_id');
        $itemIds = array_filter(array_column($data->procedures, 'treatment_item_id'));
        $itemProcedures = $itemIds === [] ? [] : TreatmentItem::query()->whereKey($itemIds)->pluck('procedure_id')->all();

        $needsAnesthesia = $data->anesthesiaUsed
            || Procedure::query()->whereKey([...$procedureIds, ...$itemProcedures])->where('requires_anesthesia', true)->exists();

        if (! $needsAnesthesia) {
            return;
        }

        if (Allergy::query()->whereBelongsTo($patient)->active()->exists()) {
            throw ValidationException::withMessages([
                'allergies_acknowledged' => 'El paciente tiene alergias registradas: confirme que las revisó antes de aplicar anestesia.',
            ]);
        }
    }
}
