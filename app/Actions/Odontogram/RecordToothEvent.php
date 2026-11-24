<?php

namespace App\Actions\Odontogram;

use App\Data\ToothEventData;
use App\Models\Patient;
use App\Models\ToothEvent;
use App\Models\User;

class RecordToothEvent
{
    public function handle(Patient $patient, User $professional, ToothEventData $data): ToothEvent
    {
        return ToothEvent::create([
            ...$data->attributes(),
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
        ]);
    }
}
