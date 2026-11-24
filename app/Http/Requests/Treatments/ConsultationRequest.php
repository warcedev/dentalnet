<?php

namespace App\Http\Requests\Treatments;

use App\Data\ConsultationData;
use App\Enums\ToothSurface;
use App\Models\Consultation;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return ! $patient->trashed() && $this->user()->can('create', Consultation::class);
    }

    protected function prepareForValidation(): void
    {
        $procedures = array_values(array_filter((array) $this->input('procedures', []), fn ($p) => is_array($p) && (filled($p['procedure_id'] ?? null) || filled($p['treatment_item_id'] ?? null))));

        $this->merge([
            'procedures' => array_map(fn (array $p) => [
                'procedure_id' => filled($p['procedure_id'] ?? null) ? $p['procedure_id'] : null,
                'treatment_item_id' => filled($p['treatment_item_id'] ?? null) ? $p['treatment_item_id'] : null,
                'tooth_code' => filled($p['tooth_code'] ?? null) ? $p['tooth_code'] : null,
                'surface' => filled($p['surface'] ?? null) ? $p['surface'] : null,
                'notes' => filled($p['notes'] ?? null) ? $p['notes'] : null,
            ], $procedures),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'consulted_at' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'max:500'],
            'subjective' => ['nullable', 'string', 'max:5000'],
            'objective' => ['nullable', 'string', 'max:5000'],
            'assessment' => ['nullable', 'string', 'max:5000'],
            'plan' => ['nullable', 'string', 'max:5000'],
            'materials' => ['nullable', 'string', 'max:2000'],
            'observations' => ['nullable', 'string', 'max:5000'],
            'next_visit' => ['nullable', 'string', 'max:200'],
            'next_visit_on' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'anesthesia_used' => ['boolean'],
            'allergies_acknowledged' => ['boolean'],
            'appointment_id' => ['nullable', 'integer', Rule::exists('appointments', 'id')->where('patient_id', $patient->id)],
            'procedures' => ['array', 'max:20'],
            'procedures.*.procedure_id' => ['nullable', 'required_without:procedures.*.treatment_item_id', 'integer', Rule::exists('procedures', 'id')],
            'procedures.*.treatment_item_id' => ['nullable', 'integer'],
            'procedures.*.tooth_code' => ['nullable', 'integer', Rule::exists('teeth', 'code')],
            'procedures.*.surface' => ['nullable', Rule::enum(ToothSurface::class)],
            'procedures.*.notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function toData(): ConsultationData
    {
        $text = fn (string $key) => $this->filled($key) ? $this->string($key)->trim()->value() : null;

        /** @var list<array{procedure_id: ?string, treatment_item_id: ?string, tooth_code: ?string, surface: ?string, notes: ?string}> $rows */
        $rows = (array) $this->input('procedures', []);

        return new ConsultationData(
            consultedAt: CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $this->input('consulted_at')) ?: CarbonImmutable::now(),
            reason: $this->string('reason')->trim()->value(),
            subjective: $text('subjective'),
            objective: $text('objective'),
            assessment: $text('assessment'),
            plan: $text('plan'),
            materials: $text('materials'),
            observations: $text('observations'),
            nextVisit: $text('next_visit'),
            nextVisitOn: $this->filled('next_visit_on') ? (CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('next_visit_on')) ?: null) : null,
            anesthesiaUsed: $this->boolean('anesthesia_used'),
            allergiesAcknowledged: $this->boolean('allergies_acknowledged'),
            appointmentId: $this->filled('appointment_id') ? $this->integer('appointment_id') : null,
            procedures: array_map(fn (array $r) => [
                'procedure_id' => (int) $r['procedure_id'],
                'treatment_item_id' => $r['treatment_item_id'] === null ? null : (int) $r['treatment_item_id'],
                'tooth_code' => $r['tooth_code'] === null ? null : (int) $r['tooth_code'],
                'surface' => $r['surface'],
                'notes' => $r['notes'],
            ], $rows),
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'consulted_at' => 'fecha y hora', 'reason' => 'motivo', 'subjective' => 'subjetivo', 'objective' => 'objetivo',
            'assessment' => 'evaluación', 'plan' => 'plan', 'materials' => 'materiales', 'observations' => 'observaciones',
            'next_visit' => 'próxima visita', 'next_visit_on' => 'fecha sugerida',
            'procedures.*.procedure_id' => 'procedimiento', 'procedures.*.tooth_code' => 'diente', 'procedures.*.surface' => 'superficie',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'consulted_at.before_or_equal' => 'La consulta no puede tener fecha futura.',
            'next_visit_on.after' => 'La fecha sugerida para la próxima visita debe ser futura.',
            'procedures.*.procedure_id.required_without' => 'Seleccione el procedimiento.',
        ];
    }
}
