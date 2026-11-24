<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'usuario' => ['nullable', 'integer'],
            'evento' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ], attributes: ['desde' => 'fecha desde', 'hasta' => 'fecha hasta']);

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['usuario'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['evento'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($filters['desde'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['hasta'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at->toIso8601String(),
                'user' => $log->user?->name,
                'event' => $log->event,
                'event_label' => AuditLabels::event($log->event),
                'model' => AuditLabels::model($log->auditable_type),
                'model_id' => $log->auditable_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
            ]);

        return Inertia::render('admin/audit/index', [
            'logs' => $logs,
            'filters' => [
                'usuario' => isset($filters['usuario']) ? (string) $filters['usuario'] : '',
                'evento' => $filters['evento'] ?? '',
                'desde' => $filters['desde'] ?? '',
                'hasta' => $filters['hasta'] ?? '',
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'events' => collect(AuditLabels::EVENTS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]);
    }
}
