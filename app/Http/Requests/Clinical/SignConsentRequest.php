<?php

namespace App\Http\Requests\Clinical;

use App\Enums\SignerRelationship;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('collectConsent', $patient);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'consent_id' => ['required', 'integer', Rule::exists('consents', 'id')],
            'signer_name' => ['required', 'string', 'max:120'],
            'signer_document' => ['required', 'string', 'max:30'],
            'signer_relationship' => ['required', Rule::enum(SignerRelationship::class)],
            'accepted' => ['accepted'],
            'signature' => ['required', 'string', 'max:450000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'consent_id' => 'consentimiento',
            'signer_name' => 'nombre de quien firma',
            'signer_document' => 'documento de quien firma',
            'signer_relationship' => 'vínculo con el paciente',
            'signature' => 'firma',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'accepted.accepted' => 'Debe confirmar que el paciente leyó y comprendió el consentimiento.',
            'signature.required' => 'Falta la firma.',
        ];
    }
}
