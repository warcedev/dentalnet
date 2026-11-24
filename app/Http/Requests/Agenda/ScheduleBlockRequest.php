<?php

namespace App\Http\Requests\Agenda;

use App\Enums\Role;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ScheduleBlock::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'professional_id' => ['nullable', 'integer', Rule::in(User::role(Role::Dentist->value)->pluck('id')->all())],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:160'],
        ];
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->input('starts_at')) ?: CarbonImmutable::now();
    }

    public function end(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->input('ends_at')) ?: CarbonImmutable::now();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['professional_id' => 'profesional', 'room_id' => 'consultorio', 'starts_at' => 'inicio', 'ends_at' => 'fin', 'reason' => 'motivo'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ends_at.after' => 'El fin del bloqueo debe ser posterior al inicio.'];
    }
}
