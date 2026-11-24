<?php

namespace App\Http\Requests\Admin;

use App\Models\Consent;
use Illuminate\Foundation\Http\FormRequest;

class ConsentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $consent = $this->route('consent');

        return $consent instanceof Consent
            ? $this->user()->can('update', $consent)
            : $this->user()->can('create', Consent::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:50', 'max:30000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['title' => 'título', 'body' => 'texto del consentimiento'];
    }
}
