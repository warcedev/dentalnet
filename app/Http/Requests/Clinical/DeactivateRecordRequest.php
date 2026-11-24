<?php

namespace App\Http\Requests\Clinical;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateRecordRequest extends FormRequest
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
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => 'motivo'];
    }
}
