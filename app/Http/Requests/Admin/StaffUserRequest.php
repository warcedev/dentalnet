<?php

namespace App\Http\Requests\Admin;

use App\Data\StaffUserData;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validación compartida por el alta y la edición de usuarios del personal.
 */
class StaffUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'string', 'lowercase', 'email:rfc', 'max:190',
                Rule::unique(User::class)->ignore($target instanceof User ? $target->id : null),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::enum(Role::class)],
            'is_active' => [$target instanceof User ? 'required' : 'sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        // Un odontólogo necesita registro profesional (se imprime en las recetas).
        $validator->sometimes('license_number', 'required', fn ($input) => in_array(Role::Dentist->value, (array) ($input->roles ?? []), true));
    }

    public function toData(): StaffUserData
    {
        /** @var list<string> $roles */
        $roles = array_values(array_map('strval', (array) $this->validated('roles')));

        return new StaffUserData(
            name: $this->string('name')->trim()->value(),
            email: $this->string('email')->lower()->value(),
            phone: $this->filled('phone') ? $this->string('phone')->trim()->value() : null,
            licenseNumber: $this->filled('license_number') ? $this->string('license_number')->trim()->value() : null,
            roles: $roles,
            isActive: $this->has('is_active') ? $this->boolean('is_active') : true,
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'phone' => 'teléfono',
            'license_number' => 'registro profesional',
            'roles' => 'roles',
            'roles.*' => 'rol',
            'is_active' => 'estado',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'license_number.required' => 'El registro profesional es obligatorio para los odontólogos.',
            'roles.required' => 'Seleccione al menos un rol.',
            'phone.regex' => 'El teléfono solo puede contener números, espacios y los signos + - ( ).',
        ];
    }
}
