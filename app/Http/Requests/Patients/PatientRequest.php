<?php

namespace App\Http\Requests\Patients;

use App\Data\PatientData;
use App\Enums\DocumentType;
use App\Enums\Sex;
use App\Enums\SignerRelationship;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PatientRequest extends FormRequest
{
    private const PHONE = 'regex:/^[0-9+\-\s()]{6,30}$/';

    public function authorize(): bool
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient
            ? $this->user()->can('update', $patient)
            : $this->user()->can('create', Patient::class);
    }

    protected function prepareForValidation(): void
    {
        $trim = fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v;
        $data = array_map($trim, $this->all());

        // Las cédulas se escriben con puntos (1.234.567): se guardan sin ellos.
        if (($data['document_type'] ?? null) === DocumentType::Ci->value && is_string($data['document_number'] ?? null)) {
            $data['document_number'] = preg_replace('/[.\s]/', '', $data['document_number']);
        }

        if (is_string($data['email'] ?? null)) {
            $data['email'] = mb_strtolower($data['email']);
        }

        $this->replace($data);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'document_number' => ['required', 'string', 'max:30', 'regex:/^[0-9A-Za-z\-]+$/', $this->uniqueDocument()],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'phone' => ['nullable', 'string', self::PHONE],
            'phone_alt' => ['nullable', 'string', self::PHONE],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'address' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:80'],
            'occupation' => ['nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'required_with:emergency_contact_name', 'string', self::PHONE],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:40'],
            'insurer' => ['nullable', 'string', 'max:80'],
            'insurance_number' => ['nullable', 'string', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_document' => ['nullable', 'string', 'max:30'],
            'guardian_relationship' => ['nullable', Rule::enum(SignerRelationship::class)->except([SignerRelationship::Patient])],
            'guardian_phone' => ['nullable', 'string', self::PHONE],
            'administrative_notes' => ['nullable', 'string', 'max:2000'],
            'accepts_communications' => ['boolean'],
        ];
    }

    /** Un menor de edad debe tener registrado a su tutor. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $birth = $this->input('birth_date');

            if (! is_string($birth) || $v->errors()->has('birth_date')) {
                return;
            }

            $age = (int) CarbonImmutable::createFromFormat('Y-m-d', $birth)?->diffInYears(now()->startOfDay());

            if ($age >= Patient::ADULT_AGE) {
                return;
            }

            foreach (['guardian_name', 'guardian_document', 'guardian_relationship'] as $field) {
                if (blank($this->input($field))) {
                    $v->errors()->add($field, 'Es obligatorio porque el paciente es menor de edad.');
                }
            }
        });
    }

    private function uniqueDocument(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $current = $this->route('patient');
            $currentId = $current instanceof Patient ? $current->id : null;

            $existing = Patient::withTrashed()
                ->where('document_type', $this->input('document_type'))
                ->where('document_number', $value)
                ->when($currentId !== null, fn ($q) => $q->whereKeyNot($currentId))
                ->first();

            if ($existing === null) {
                return;
            }

            $fail($existing->trashed()
                ? 'Ya existe un paciente archivado con ese documento. Pida al administrador que lo restaure.'
                : 'Ya existe un paciente con ese documento.');
        };
    }

    public function toData(): PatientData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $validated['accepts_communications'] = $this->boolean('accepts_communications');

        return new PatientData($validated);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombres',
            'last_name' => 'apellidos',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'birth_date' => 'fecha de nacimiento',
            'sex' => 'sexo',
            'phone' => 'teléfono',
            'phone_alt' => 'teléfono alternativo',
            'email' => 'correo electrónico',
            'address' => 'dirección',
            'city' => 'ciudad',
            'occupation' => 'ocupación',
            'emergency_contact_name' => 'contacto de emergencia',
            'emergency_contact_phone' => 'teléfono de emergencia',
            'emergency_contact_relationship' => 'parentesco',
            'insurer' => 'obra social o prepaga',
            'insurance_number' => 'número de afiliado',
            'guardian_name' => 'nombre del tutor',
            'guardian_document' => 'documento del tutor',
            'guardian_relationship' => 'vínculo del tutor',
            'guardian_phone' => 'teléfono del tutor',
            'administrative_notes' => 'notas',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'document_number.regex' => 'El documento solo puede contener letras, números y guiones.',
            'regex' => 'El :attribute solo puede contener números, espacios y los signos + - ( ).',
        ];
    }
}
