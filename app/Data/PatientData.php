<?php

namespace App\Data;

/**
 * Datos validados de un paciente, listos para guardar.
 */
final readonly class PatientData
{
    /** @param  array<string, mixed>  $attributes */
    public function __construct(public array $attributes) {}
}
