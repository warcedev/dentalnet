<?php

namespace App\Http\Requests\Clinical;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

class MedicalHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('updateClinicalRecord', $patient);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');
        $isFirst = ! $patient->medicalHistories()->exists();

        return [
            'medical_background' => ['nullable', 'string', 'max:5000'],
            'surgical_history' => ['nullable', 'string', 'max:5000'],
            'current_medication' => ['nullable', 'string', 'max:5000'],
            'habits' => ['nullable', 'string', 'max:2000'],
            'observations' => ['nullable', 'string', 'max:5000'],
            // Al corregir una versión anterior hay que explicar el motivo.
            'change_reason' => [$isFirst ? 'nullable' : 'required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'medical_background' => 'antecedentes médicos',
            'surgical_history' => 'antecedentes quirúrgicos',
            'current_medication' => 'medicación actual',
            'habits' => 'hábitos',
            'observations' => 'observaciones',
            'change_reason' => 'motivo del cambio',
        ];
    }
}
