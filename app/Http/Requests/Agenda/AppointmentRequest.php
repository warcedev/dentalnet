<?php

namespace App\Http\Requests\Agenda;

use App\Data\AppointmentData;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            ? $this->user()->can('update', $appointment)
            : $this->user()->can('create', Appointment::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = ! $this->route('appointment') instanceof Appointment;

        return [
            'patient_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'professional_id' => ['required', 'integer', Rule::in($this->activeDentistIds())],
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'appointment_type_id' => ['required', 'integer', Rule::exists('appointment_types', 'id')->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'waiting_list_entry_id' => ['nullable', 'integer', Rule::exists('waiting_list', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->hasAny(['date', 'start_time'])) {
                return;
            }

            if ($this->startsAt()->lt(now()->subMinutes(5))) {
                $v->errors()->add('start_time', 'No se puede agendar en un horario que ya pasó.');
            }
        });
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->input('date').' '.$this->input('start_time'))
            ?: CarbonImmutable::now();
    }

    public function toData(?int $patientId = null): AppointmentData
    {
        return new AppointmentData(
            patientId: $patientId ?? $this->integer('patient_id'),
            professionalId: $this->integer('professional_id'),
            roomId: $this->integer('room_id'),
            typeId: $this->integer('appointment_type_id'),
            startsAt: $this->startsAt()->setSecond(0),
            durationMinutes: $this->integer('duration_minutes'),
            notes: $this->filled('notes') ? $this->string('notes')->trim()->value() : null,
            waitingListEntryId: $this->filled('waiting_list_entry_id') ? $this->integer('waiting_list_entry_id') : null,
        );
    }

    /** @return list<int> */
    private function activeDentistIds(): array
    {
        return array_values(User::role(Role::Dentist->value)->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'professional_id' => 'profesional',
            'room_id' => 'consultorio',
            'appointment_type_id' => 'tipo de cita',
            'date' => 'fecha',
            'start_time' => 'hora',
            'duration_minutes' => 'duración',
            'notes' => 'notas',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'professional_id.in' => 'Seleccione un odontólogo activo.',
            'patient_id.prohibited' => 'No se puede cambiar el paciente de una cita: cancélela y agende una nueva.',
        ];
    }
}
