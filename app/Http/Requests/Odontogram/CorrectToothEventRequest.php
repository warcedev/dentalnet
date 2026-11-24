<?php

namespace App\Http\Requests\Odontogram;

use Illuminate\Validation\Validator;

class CorrectToothEventRequest extends ToothEventRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $common = [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'void' => ['boolean'],
        ];

        // Anular no necesita los datos del evento: se copian del original.
        return $this->boolean('void') ? $common : [...parent::rules(), ...$common];
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->boolean('void')) {
            parent::withValidator($validator);
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [...parent::attributes(), 'reason' => 'motivo de la corrección'];
    }
}
