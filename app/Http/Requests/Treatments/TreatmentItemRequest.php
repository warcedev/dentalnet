<?php

namespace App\Http\Requests\Treatments;

use App\Enums\ToothSurface;
use App\Models\TreatmentItem;
use App\Models\TreatmentPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TreatmentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TreatmentPlan $plan */
        $plan = $this->route('treatmentPlan');

        return $this->user()->can('update', $plan);
    }

    protected function prepareForValidation(): void
    {
        // Los montos llegan como "1.250.000" o "Gs. 1.250.000": se guardan como entero.
        $price = $this->input('price');

        $this->merge([
            'price' => is_string($price) ? preg_replace('/\D/', '', $price) : $price,
            'tooth_code' => $this->filled('tooth_code') ? $this->input('tooth_code') : null,
            'surface' => $this->filled('surface') ? $this->input('surface') : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = ! $this->route('treatmentItem') instanceof TreatmentItem;

        return [
            'procedure_id' => [$creating ? 'required' : 'prohibited', 'integer', Rule::exists('procedures', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:200'],
            'tooth_code' => ['nullable', 'integer', Rule::exists('teeth', 'code')],
            'surface' => ['nullable', Rule::enum(ToothSurface::class), 'required_with_all:tooth_code,surface'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:240'],
        ];
    }

    /** @return array{procedure_id: int, description: ?string, tooth_code: ?int, surface: ?string, price: int, warranty_months: ?int} */
    public function itemData(): array
    {
        return [
            'procedure_id' => $this->integer('procedure_id'),
            'description' => $this->filled('description') ? $this->string('description')->trim()->value() : null,
            'tooth_code' => $this->input('tooth_code') === null ? null : $this->integer('tooth_code'),
            'surface' => $this->input('surface') === null ? null : (string) $this->input('surface'),
            'price' => $this->integer('price'),
            'warranty_months' => $this->filled('warranty_months') ? $this->integer('warranty_months') : null,
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'procedure_id' => 'procedimiento', 'description' => 'descripción', 'tooth_code' => 'diente',
            'surface' => 'superficie', 'price' => 'precio', 'warranty_months' => 'garantía',
        ];
    }
}
