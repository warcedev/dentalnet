<?php

namespace App\Actions\Documents;

use App\Enums\PrescriptionType;
use App\Models\Allergy;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class IssuePrescription
{
    /**
     * @param  list<array{drug: string, dose: string, frequency: string, duration: string}>  $medications
     */
    public function handle(
        Patient $patient,
        User $professional,
        PrescriptionType $type,
        array $medications,
        ?string $instructions,
        bool $allergiesAcknowledged,
        ?int $consultationId = null,
    ): Prescription {
        // Regla 6: con alergias registradas, toda receta exige confirmar que se revisaron.
        $hasAllergies = Allergy::query()->whereBelongsTo($patient)->active()->exists();

        if ($type === PrescriptionType::Prescription && $hasAllergies && ! $allergiesAcknowledged) {
            throw ValidationException::withMessages([
                'allergies_acknowledged' => 'El paciente tiene alergias registradas: confirme que las revisó antes de recetar.',
            ]);
        }

        return Prescription::create([
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'consultation_id' => $consultationId,
            'type' => $type,
            'medications' => $type === PrescriptionType::Prescription ? $medications : null,
            'instructions' => $instructions,
            'allergies_acknowledged_at' => $allergiesAcknowledged && $hasAllergies ? now() : null,
            'issued_at' => now(),
        ]);
    }
}
