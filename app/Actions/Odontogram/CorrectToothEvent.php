<?php

namespace App\Actions\Odontogram;

use App\Data\ToothEventData;
use App\Models\ToothEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Regla de negocio 1: el historial no se sobrescribe. Corregir o anular un
 * evento crea un evento nuevo que lo referencia; el original queda intacto.
 */
class CorrectToothEvent
{
    public function correct(ToothEvent $original, User $professional, ToothEventData $data, string $reason): ToothEvent
    {
        return $this->create($original, $professional, $reason, [...$data->attributes(), 'is_void' => false]);
    }

    /** Anula un evento cargado por error. */
    public function void(ToothEvent $original, User $professional, string $reason): ToothEvent
    {
        return $this->create($original, $professional, $reason, [
            'tooth_code' => $original->tooth_code,
            'surface' => $original->surface,
            'condition' => $original->condition,
            'status' => $original->status,
            'detected_on' => $original->detected_on,
            'treated_on' => $original->treated_on,
            'notes' => null,
            'is_void' => true,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function create(ToothEvent $original, User $professional, string $reason, array $attributes): ToothEvent
    {
        return DB::transaction(function () use ($original, $professional, $reason, $attributes) {
            $this->ensureCorrectable($original);

            return ToothEvent::create([
                ...$attributes,
                'patient_id' => $original->patient_id,
                'professional_id' => $professional->id,
                'corrects_event_id' => $original->id,
                'correction_reason' => $reason,
            ]);
        });
    }

    private function ensureCorrectable(ToothEvent $original): void
    {
        if ($original->is_void) {
            throw ValidationException::withMessages(['event' => 'Una anulación no se puede corregir.']);
        }

        if (ToothEvent::query()->where('corrects_event_id', $original->id)->exists()) {
            throw ValidationException::withMessages(['event' => 'Este registro ya fue corregido. Corrija el registro más reciente.']);
        }
    }
}
