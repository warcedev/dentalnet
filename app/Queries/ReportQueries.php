<?php

namespace App\Queries;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de los reportes. Usan solo SQL estándar a través del Query
 * Builder para funcionar igual en SQLite, MySQL y PostgreSQL.
 */
class ReportQueries
{
    private string $from;

    private string $to;

    public function __construct(CarbonImmutable $from, CarbonImmutable $to)
    {
        $this->from = $from->startOfDay()->format('Y-m-d H:i:s');
        $this->to = $to->endOfDay()->format('Y-m-d H:i:s');
    }

    /** @return array{patients: int, consultations: int, attended_appointments: int} */
    public function attended(): array
    {
        $fromConsultations = DB::table('consultations')->whereBetween('consulted_at', [$this->from, $this->to])->select('patient_id');
        $fromAppointments = DB::table('appointments')->whereBetween('starts_at', [$this->from, $this->to])
            ->where('status', AppointmentStatus::Attended->value)->select('patient_id');

        return [
            'patients' => DB::query()->fromSub($fromConsultations->union($fromAppointments), 'p')->distinct()->count('patient_id'),
            'consultations' => DB::table('consultations')->whereBetween('consulted_at', [$this->from, $this->to])->count(),
            'attended_appointments' => DB::table('appointments')->whereBetween('starts_at', [$this->from, $this->to])
                ->where('status', AppointmentStatus::Attended->value)->count(),
        ];
    }

    /** @return list<array{procedure: string, count: int}> */
    public function topProcedures(int $limit = 10): array
    {
        return array_values(DB::table('consultation_procedures as cp')
            ->join('consultations as c', 'c.id', '=', 'cp.consultation_id')
            ->join('procedures as p', 'p.id', '=', 'cp.procedure_id')
            ->whereBetween('c.consulted_at', [$this->from, $this->to])
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total')
            ->orderBy('p.name')
            ->limit($limit)
            ->get(['p.name as procedure', DB::raw('count(*) as total')])
            ->map(fn ($r) => ['procedure' => (string) $r->procedure, 'count' => (int) $r->total])
            ->all());
    }

    /**
     * Ingresos por profesional: se atribuye al profesional del plan al que se
     * imputó el pago. Los pagos a cuenta sin plan quedan "Sin asignar".
     *
     * @return list<array{professional: string, payments: int, total: int}>
     */
    public function incomeByProfessional(): array
    {
        return array_values(DB::table('payments as pay')
            ->leftJoin('treatment_plans as tp', 'tp.id', '=', 'pay.treatment_plan_id')
            ->leftJoin('users as u', 'u.id', '=', 'tp.professional_id')
            ->whereNull('pay.voided_at')
            ->whereBetween('pay.paid_at', [$this->from, $this->to])
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total')
            ->get(['u.name as professional', DB::raw('count(*) as payments'), DB::raw('sum(pay.amount) as total')])
            ->map(fn ($r) => ['professional' => is_string($r->professional) ? $r->professional : 'Sin asignar', 'payments' => (int) $r->payments, 'total' => (int) $r->total])
            ->all());
    }

    /** @return list<array{method: string, label: string, payments: int, total: int}> */
    public function incomeByMethod(): array
    {
        return array_values(DB::table('payments')
            ->whereNull('voided_at')
            ->whereBetween('paid_at', [$this->from, $this->to])
            ->groupBy('method')
            ->orderByDesc('total')
            ->get(['method', DB::raw('count(*) as payments'), DB::raw('sum(amount) as total')])
            ->map(fn ($r) => [
                'method' => (string) $r->method,
                'label' => PaymentMethod::tryFrom((string) $r->method)?->label() ?? (string) $r->method,
                'payments' => (int) $r->payments,
                'total' => (int) $r->total,
            ])
            ->all());
    }

    /**
     * Inasistencias sobre las citas que ya debían ocurrir (atendidas + no asistió).
     *
     * @return array{no_show: int, attended: int, rate: float}
     */
    public function noShowRate(): array
    {
        $counts = DB::table('appointments')
            ->whereBetween('starts_at', [$this->from, $this->to])
            ->whereIn('status', [AppointmentStatus::Attended->value, AppointmentStatus::NoShow->value])
            ->groupBy('status')
            ->pluck(DB::raw('count(*)'), 'status');

        $noShow = (int) ($counts[AppointmentStatus::NoShow->value] ?? 0);
        $attended = (int) ($counts[AppointmentStatus::Attended->value] ?? 0);
        $total = $noShow + $attended;

        return ['no_show' => $noShow, 'attended' => $attended, 'rate' => $total > 0 ? round($noShow / $total * 100, 1) : 0.0];
    }

    /**
     * Pacientes activos cuya última consulta o cita atendida fue hace más de
     * N meses (o que nunca vinieron). No depende del período del reporte.
     *
     * @return list<array{id: int, name: string, phone: string|null, last_visit: string|null, accepts_communications: bool}>
     */
    public static function withoutControl(int $months = 6, ?CarbonImmutable $today = null): array
    {
        $limit = ($today ?? CarbonImmutable::today())->subMonthsNoOverflow($months)->format('Y-m-d H:i:s');

        $visits = DB::table('consultations')->select('patient_id', 'consulted_at as visited_at')
            ->unionAll(DB::table('appointments')->where('status', AppointmentStatus::Attended->value)->select('patient_id', 'starts_at as visited_at'));

        $lastVisit = DB::query()->fromSub($visits, 'v')->groupBy('patient_id')->select('patient_id', DB::raw('max(visited_at) as last_visit'));

        return array_values(DB::table('patients as p')
            ->leftJoinSub($lastVisit, 'lv', 'lv.patient_id', '=', 'p.id')
            ->whereNull('p.deleted_at')
            ->where(fn ($q) => $q->whereNull('lv.last_visit')->orWhere('lv.last_visit', '<', $limit))
            ->orderByRaw('lv.last_visit is null')
            ->orderBy('lv.last_visit')
            ->orderBy('p.last_name')
            ->get(['p.id', 'p.first_name', 'p.last_name', 'p.phone', 'p.accepts_communications', 'lv.last_visit'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => trim($r->first_name.' '.$r->last_name),
                'phone' => is_string($r->phone) ? $r->phone : null,
                'last_visit' => $r->last_visit !== null ? substr((string) $r->last_visit, 0, 10) : null,
                'accepts_communications' => (bool) $r->accepts_communications,
            ])
            ->all());
    }
}
