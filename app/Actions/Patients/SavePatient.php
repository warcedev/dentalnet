<?php

namespace App\Actions\Patients;

use App\Data\PatientData;
use App\Models\Patient;
use App\Models\User;

class SavePatient
{
    public function create(PatientData $data, User $actor): Patient
    {
        $patient = new Patient($data->attributes);
        $patient->created_by = $actor->id;
        $patient->save();

        return $patient;
    }

    public function update(Patient $patient, PatientData $data): Patient
    {
        $patient->update($data->attributes);

        return $patient;
    }
}
