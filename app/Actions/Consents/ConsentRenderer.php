<?php

namespace App\Actions\Consents;

use App\Models\Consent;
use App\Models\Patient;
use App\Models\User;

/**
 * Reemplaza los marcadores de la plantilla con los datos del paciente.
 *
 * Marcadores disponibles: {paciente}, {documento}, {edad}, {fecha}, {profesional}, {consultorio}.
 */
class ConsentRenderer
{
    public const PLACEHOLDERS = ['{paciente}', '{documento}', '{edad}', '{fecha}', '{profesional}', '{consultorio}'];

    public function render(Consent $consent, Patient $patient, User $professional): string
    {
        return strtr($consent->body, [
            '{paciente}' => $patient->full_name,
            '{documento}' => $patient->document_type->label().' '.$patient->document_number,
            '{edad}' => (string) $patient->age,
            '{fecha}' => now()->format('d/m/Y'),
            '{profesional}' => $professional->name,
            '{consultorio}' => (string) config('app.name'),
        ]);
    }
}
