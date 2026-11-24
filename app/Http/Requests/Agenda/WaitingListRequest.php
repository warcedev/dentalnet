<?php

namespace App\Http\Requests\Agenda;

use App\Enums\Role;
use App\Models\User;
use App\Models\WaitingListEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WaitingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WaitingListEntry::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'appointment_type_id' => ['nullable', 'integer', Rule::exists('appointment_types', 'id')],
            'professional_id' => ['nullable', 'integer', Rule::in(User::role(Role::Dentist->value)->pluck('id')->all())],
            'preference' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['patient_id' => 'paciente', 'appointment_type_id' => 'tipo de cita', 'professional_id' => 'profesional', 'preference' => 'preferencia de horario', 'notes' => 'notas'];
    }
}
