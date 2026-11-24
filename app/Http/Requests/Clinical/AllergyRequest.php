<?php

namespace App\Http\Requests\Clinical;

use App\Enums\AllergyType;
use App\Enums\Severity;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllergyRequest extends FormRequest
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
        return [
            'type' => ['required', Rule::enum(AllergyType::class)],
            'allergen' => ['required', 'string', 'max:120'],
            'severity' => ['required', Rule::enum(Severity::class)],
            'reaction' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['type' => 'tipo', 'allergen' => 'alérgeno', 'severity' => 'gravedad', 'reaction' => 'reacción'];
    }
}
