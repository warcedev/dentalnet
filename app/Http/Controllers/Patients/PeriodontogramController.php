<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Http\Presenters\PatientPresenter;
use App\Models\Patient;
use App\Models\PeriodontalMeasurement;
use App\Models\PeriodontalRecord;
use App\Models\Tooth;
use App\Services\Periodontal\PeriodontalSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PeriodontogramController extends Controller
{
    public function __construct(
        private readonly PatientPresenter $presenter,
        private readonly PeriodontalSummary $summary,
    ) {}

    public function index(Request $request, Patient $patient): Response
    {
        Gate::authorize('view', $patient);
        Gate::authorize('viewOdontogram', $patient);

        $records = $patient->periodontalRecords()->with(['measurements', 'professional:id,name'])->orderBy('recorded_on')->orderBy('id')->get();
        $selectedId = $request->integer('registro') ?: $records->last()?->id;
        $selected = $records->firstWhere('id', $selectedId);

        return Inertia::render('patients/periodontogram', [
            'header' => $this->presenter->header($patient, $request->user('web')),
            'teeth' => Tooth::query()->where('dentition', 'permanente')->orderBy('code')->pluck('code'),
            'records' => $records->map(fn (PeriodontalRecord $r) => [
                'id' => $r->id,
                'recorded_on' => $r->recorded_on->toDateString(),
                'professional' => $r->professional->name,
                'summary' => $this->summary->for($r),
            ])->values(),
            'selected' => $selected ? [
                'id' => $selected->id,
                'recorded_on' => $selected->recorded_on->toDateString(),
                'professional' => $selected->professional->name,
                'notes' => $selected->notes,
                'measurements' => $selected->measurements->mapWithKeys(fn (PeriodontalMeasurement $m) => [$m->tooth_code => [
                    'is_missing' => $m->is_missing,
                    'probing_depth' => $m->probing_depth,
                    'recession' => $m->recession,
                    'bleeding' => $m->bleeding,
                    'plaque' => $m->plaque,
                    'mobility' => $m->mobility,
                ]]),
            ] : null,
            'can_record' => $request->user('web')->can('recordOdontogram', $patient),
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        Gate::authorize('recordOdontogram', $patient);

        $data = $request->validate([
            'recorded_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'teeth' => ['required', 'array'],
            'teeth.*.is_missing' => ['boolean'],
            'teeth.*.probing_depth' => ['array', 'size:6'],
            'teeth.*.probing_depth.*' => ['nullable', 'integer', 'min:0', 'max:15'],
            'teeth.*.recession' => ['array', 'size:6'],
            'teeth.*.recession.*' => ['nullable', 'integer', 'min:-5', 'max:15'],
            'teeth.*.bleeding' => ['array', 'size:6'],
            'teeth.*.bleeding.*' => ['boolean'],
            'teeth.*.plaque' => ['array', 'size:6'],
            'teeth.*.plaque.*' => ['boolean'],
            'teeth.*.mobility' => ['nullable', 'integer', 'min:0', 'max:3'],
        ], [
            'teeth.*.probing_depth.*.max' => 'La profundidad de sondaje no puede superar 15 mm.',
            'teeth.*.mobility.max' => 'La movilidad va de 0 a 3.',
        ], ['recorded_on' => 'fecha del examen', 'notes' => 'notas']);

        $permanent = Tooth::query()->where('dentition', 'permanente')->pluck('code')->map(fn ($c) => (int) $c)->all();
        $rows = [];

        foreach ($data['teeth'] as $code => $tooth) {
            if (! in_array((int) $code, $permanent, true)) {
                throw ValidationException::withMessages(['teeth' => "El diente {$code} no es una pieza permanente."]);
            }

            $hasData = ($tooth['is_missing'] ?? false)
                || array_filter($tooth['probing_depth'] ?? [], fn ($v) => $v !== null)
                || ($tooth['mobility'] ?? null) !== null;

            if ($hasData) {
                $rows[(int) $code] = $tooth;
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['teeth' => 'Registre al menos un diente.']);
        }

        DB::transaction(function () use ($patient, $request, $data, $rows) {
            $record = $patient->periodontalRecords()->create([
                'professional_id' => $request->user('web')->id,
                'recorded_on' => $data['recorded_on'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($rows as $code => $t) {
                $missing = (bool) ($t['is_missing'] ?? false);
                $record->measurements()->create([
                    'tooth_code' => $code,
                    'is_missing' => $missing,
                    'probing_depth' => $missing ? null : array_map(fn ($v) => $v === null ? null : (int) $v, $t['probing_depth'] ?? array_fill(0, 6, null)),
                    'recession' => $missing ? null : array_map(fn ($v) => $v === null ? null : (int) $v, $t['recession'] ?? array_fill(0, 6, null)),
                    'bleeding' => $missing ? null : array_map(fn ($v) => (bool) $v, $t['bleeding'] ?? array_fill(0, 6, false)),
                    'plaque' => $missing ? null : array_map(fn ($v) => (bool) $v, $t['plaque'] ?? array_fill(0, 6, false)),
                    'mobility' => $missing ? null : ($t['mobility'] ?? null),
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Examen periodontal registrado.']);

        return to_route('patients.periodontogram', $patient);
    }
}
