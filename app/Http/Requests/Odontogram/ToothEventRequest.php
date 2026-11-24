<?php

namespace App\Http\Requests\Odontogram;

use App\Data\ToothEventData;
use App\Enums\ToothCondition;
use App\Enums\ToothConditionScope;
use App\Enums\ToothEventStatus;
use App\Enums\ToothSurface;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ToothEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('recordOdontogram', $patient);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tooth_code' => $this->filled('tooth_code') ? $this->input('tooth_code') : null,
            'surface' => $this->filled('surface') ? $this->input('surface') : null,
            'treated_on' => $this->filled('treated_on') ? $this->input('treated_on') : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tooth_code' => ['nullable', 'integer', Rule::exists('teeth', 'code')],
            'surface' => ['nullable', Rule::enum(ToothSurface::class)],
            'condition' => ['required', Rule::enum(ToothCondition::class)],
            'status' => ['required', Rule::enum(ToothEventStatus::class)],
            'detected_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'treated_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:detected_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $condition = ToothCondition::from((string) $this->input('condition'));
            $status = ToothEventStatus::from((string) $this->input('status'));
            $tooth = $this->input('tooth_code');
            $surface = $this->input('surface');

            match ($condition->scope()) {
                ToothConditionScope::Surface => [
                    $tooth === null ? $v->errors()->add('tooth_code', 'Seleccione el diente.') : null,
                    $surface === null ? $v->errors()->add('surface', 'Esta condición se registra sobre una superficie: selecciónela.') : null,
                ],
                ToothConditionScope::Tooth => [
                    $tooth === null ? $v->errors()->add('tooth_code', 'Seleccione el diente.') : null,
                    $surface !== null ? $v->errors()->add('surface', 'Esta condición corresponde al diente completo: no seleccione superficie.') : null,
                ],
                ToothConditionScope::Mouth => [
                    $tooth !== null || $surface !== null ? $v->errors()->add('condition', 'La limpieza se registra para toda la boca, sin diente.') : null,
                ],
            };

            if ($status->requiresTreatmentDate() && $this->input('treated_on') === null) {
                $v->errors()->add('treated_on', 'Indique la fecha de tratamiento.');
            }
        });
    }

    public function toData(): ToothEventData
    {
        $treatedOn = $this->input('treated_on');

        return new ToothEventData(
            toothCode: $this->input('tooth_code') === null ? null : $this->integer('tooth_code'),
            surface: $this->enum('surface', ToothSurface::class),
            condition: ToothCondition::from((string) $this->input('condition')),
            status: ToothEventStatus::from((string) $this->input('status')),
            detectedOn: CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('detected_on')) ?: CarbonImmutable::today(),
            treatedOn: is_string($treatedOn) ? (CarbonImmutable::createFromFormat('!Y-m-d', $treatedOn) ?: null) : null,
            notes: $this->filled('notes') ? $this->string('notes')->trim()->value() : null,
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tooth_code' => 'diente',
            'surface' => 'superficie',
            'condition' => 'condición',
            'status' => 'estado',
            'detected_on' => 'fecha de detección',
            'treated_on' => 'fecha de tratamiento',
            'notes' => 'notas',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'detected_on.before_or_equal' => 'La fecha de detección no puede ser futura.',
            'treated_on.before_or_equal' => 'La fecha de tratamiento no puede ser futura.',
            'treated_on.after_or_equal' => 'La fecha de tratamiento no puede ser anterior a la de detección.',
        ];
    }
}
