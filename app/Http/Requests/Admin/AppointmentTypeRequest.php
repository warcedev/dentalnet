<?php

namespace App\Http\Requests\Admin;

use App\Models\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('appointmentType');

        return $type instanceof AppointmentType ? $this->user()->can('update', $type) : $this->user()->can('create', AppointmentType::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->route('appointmentType');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('appointment_types', 'name')->ignore($type instanceof AppointmentType ? $type->id : null)],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_cleaning' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'duration_minutes' => 'duración', 'color' => 'color'];
    }
}
