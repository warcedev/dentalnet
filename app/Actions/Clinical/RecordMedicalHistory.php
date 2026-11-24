<?php

namespace App\Actions\Clinical;

use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registra una versión nueva de los antecedentes. La anterior no se toca.
 */
class RecordMedicalHistory
{
    /** @param  array{medical_background?: ?string, surgical_history?: ?string, current_medication?: ?string, habits?: ?string, observations?: ?string, change_reason?: ?string}  $data */
    public function handle(Patient $patient, User $actor, array $data): MedicalHistory
    {
        return DB::transaction(function () use ($patient, $actor, $data) {
            $next = (int) MedicalHistory::query()->where('patient_id', $patient->id)->max('version') + 1;

            return MedicalHistory::create([
                'patient_id' => $patient->id,
                'version' => $next,
                'medical_background' => $data['medical_background'] ?? null,
                'surgical_history' => $data['surgical_history'] ?? null,
                'current_medication' => $data['current_medication'] ?? null,
                'habits' => $data['habits'] ?? null,
                'observations' => $data['observations'] ?? null,
                'change_reason' => $data['change_reason'] ?? null,
                'recorded_by' => $actor->id,
            ]);
        });
    }
}
