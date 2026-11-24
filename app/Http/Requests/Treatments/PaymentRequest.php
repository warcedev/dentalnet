<?php

namespace App\Http\Requests\Treatments;

use App\Enums\PaymentMethod;
use App\Models\Patient;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Payment::class);
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        $this->merge([
            'amount' => is_string($amount) ? preg_replace('/\D/', '', $amount) : $amount,
            'treatment_plan_id' => $this->filled('treatment_plan_id') ? $this->input('treatment_plan_id') : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'treatment_plan_id' => ['nullable', 'integer', Rule::exists('treatment_plans', 'id')->where('patient_id', $patient->id)],
            'reference' => ['nullable', 'string', 'max:60', Rule::requiredIf(fn () => in_array($this->input('method'), ['transferencia', 'qr'], true))],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['amount' => 'monto', 'method' => 'forma de pago', 'treatment_plan_id' => 'plan', 'reference' => 'número de operación', 'paid_on' => 'fecha', 'notes' => 'notas'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.min' => 'El monto debe ser mayor que cero.',
            'paid_on.before_or_equal' => 'La fecha del pago no puede ser futura.',
            'reference.required' => 'Indique el número de operación de la transferencia o del QR.',
        ];
    }
}
