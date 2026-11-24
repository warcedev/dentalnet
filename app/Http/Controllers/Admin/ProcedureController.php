<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ToothCondition;
use App\Http\Controllers\Controller;
use App\Models\Procedure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProcedureController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Procedure::class);

        return Inertia::render('admin/procedures', [
            'procedures' => Procedure::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'conditions' => array_map(fn (ToothCondition $c) => ['value' => $c->value, 'label' => $c->label()], ToothCondition::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Procedure::class);
        Procedure::create([...$this->validated($request), 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento creado.']);

        return back();
    }

    public function update(Request $request, Procedure $procedure): RedirectResponse
    {
        Gate::authorize('update', $procedure);
        $procedure->update([...$this->validated($request, $procedure), 'is_active' => $request->boolean('is_active')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Procedimiento actualizado.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Procedure $procedure = null): array
    {
        $price = $request->input('default_price');
        $request->merge([
            'default_price' => is_string($price) ? preg_replace('/\D/', '', $price) : $price,
            'tooth_condition' => $request->filled('tooth_condition') ? $request->input('tooth_condition') : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('procedures', 'name')->ignore($procedure?->id)],
            'default_price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'warranty_months' => ['required', 'integer', 'min:0', 'max:240'],
            'requires_anesthesia' => ['boolean'],
            'tooth_condition' => ['nullable', Rule::enum(ToothCondition::class)],
        ], attributes: ['name' => 'nombre', 'default_price' => 'precio', 'warranty_months' => 'garantía', 'tooth_condition' => 'condición del odontograma']);

        return [...$data, 'requires_anesthesia' => $request->boolean('requires_anesthesia')];
    }
}
