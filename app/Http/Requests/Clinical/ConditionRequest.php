<?php

namespace App\Http\Requests\Clinical;

use App\Enums\ConditionType;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('updateClinicalRecord', $patient);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'condition' => [
                'required',
                Rule::enum(ConditionType::class),
                // No duplicar una condición activa (salvo "otra", que puede repetirse).
                Rule::unique('patient_conditions', 'condition')
                    ->where('patient_id', $patient->id)
                    ->where('is_active', true)
                    ->whereNot('condition', ConditionType::Other->value),
            ],
            'notes' => [
                Rule::requiredIf($this->input('condition') === ConditionType::Other->value),
                'nullable', 'string', 'max:500',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['condition' => 'condición', 'notes' => 'detalle'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['condition.unique' => 'Esa condición ya está registrada y activa.'];
    }
}
