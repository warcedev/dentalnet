<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Consents\ConsentRenderer;
use App\Actions\Consents\PublishConsentVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConsentTemplateRequest;
use App\Models\Consent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConsentTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Consent::class);

        $all = Consent::query()->withCount('signatures')->orderBy('title')->orderByDesc('version')->get();

        return Inertia::render('admin/consents/index', [
            'templates' => $all->groupBy('code')->map(function ($versions) {
                /** @var Consent $latest */
                $latest = $versions->first();

                return [
                    'id' => $latest->id,
                    'code' => $latest->code,
                    'title' => $latest->title,
                    'version' => $latest->version,
                    'is_active' => $latest->is_active,
                    'updated_at' => $latest->created_at?->toIso8601String(),
                    'signatures' => (int) $versions->sum('signatures_count'),
                    'versions' => $versions->count(),
                ];
            })->values(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Consent::class);

        return Inertia::render('admin/consents/form', [
            'template' => null,
            'placeholders' => ConsentRenderer::PLACEHOLDERS,
        ]);
    }

    public function store(ConsentTemplateRequest $request, PublishConsentVersion $action): RedirectResponse
    {
        $action->handle($request->user('web'), $request->string('title')->value(), $request->string('body')->value());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plantilla creada.']);

        return to_route('admin.consents.index');
    }

    public function edit(Consent $consent): Response
    {
        Gate::authorize('update', $consent);

        return Inertia::render('admin/consents/form', [
            'template' => $consent->only(['id', 'title', 'body', 'version', 'is_active']),
            'placeholders' => ConsentRenderer::PLACEHOLDERS,
        ]);
    }

    /** Guardar cambios publica una versión nueva; la anterior se conserva. */
    public function update(ConsentTemplateRequest $request, Consent $consent, PublishConsentVersion $action): RedirectResponse
    {
        $new = $action->handle($request->user('web'), $request->string('title')->value(), $request->string('body')->value(), $consent);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Se publicó la versión {$new->version}. Lo ya firmado no cambia."]);

        return to_route('admin.consents.index');
    }

    public function deactivate(Consent $consent, PublishConsentVersion $action): RedirectResponse
    {
        Gate::authorize('update', $consent);

        $action->deactivate($consent);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La plantilla ya no se ofrece para firmar.']);

        return to_route('admin.consents.index');
    }
}
