<?php

namespace App\Http\Controllers\Reports;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Queries\ReportQueries;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const SECTIONS = ['tratamientos', 'ingresos-profesional', 'ingresos-forma', 'sin-control'];

    public function index(Request $request): Response
    {
        [$from, $to] = $this->period($request);

        return Inertia::render('reports/index', [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ...$this->data($from, $to),
        ]);
    }

    public function csv(Request $request, string $section): StreamedResponse
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        [$from, $to] = $this->period($request);
        $data = $this->data($from, $to);

        [$headers, $rows] = match ($section) {
            'tratamientos' => [['Procedimiento', 'Cantidad'], array_map(fn ($r) => [$r['procedure'], $r['count']], $data['top_procedures'])],
            'ingresos-profesional' => [['Profesional', 'Pagos', 'Total (Gs.)'], array_map(fn ($r) => [$r['professional'], $r['payments'], $r['total']], $data['income_by_professional'])],
            'ingresos-forma' => [['Forma de pago', 'Pagos', 'Total (Gs.)'], array_map(fn ($r) => [$r['label'], $r['payments'], $r['total']], $data['income_by_method'])],
            default => [['Paciente', 'Teléfono', 'Última visita', 'Acepta recordatorios'], array_map(fn ($r) => [
                $r['name'], $r['phone'] ?? '', $r['last_visit'] ? CarbonImmutable::parse($r['last_visit'])->format('d/m/Y') : 'Nunca', $r['accepts_communications'] ? 'Sí' : 'No',
            ], $data['without_control'])],
        };

        $filename = "reporte-{$section}-{$from->format('Ymd')}-{$to->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                throw new \RuntimeException('No se pudo abrir la salida del CSV.');
            }

            // BOM y separador ";" para que Excel en español lo abra correctamente.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';', escape: '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $this->safeCell($v), $row), ';', escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    public function pdf(Request $request): HttpResponse
    {
        [$from, $to] = $this->period($request);

        $response = Pdf::loadView('pdf.report', [
            'from' => $from,
            'to' => $to,
            'clinic' => config('app.name'),
            ...$this->data($from, $to),
        ])->setPaper('a4')->stream("reporte-{$from->format('Ymd')}-{$to->format('Ymd')}.pdf");

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        abort_unless($request->user('web')?->can(Permission::ReportsView->value), 403);

        $data = $request->validate([
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('desde'), 'after_or_equal:desde')],
        ], ['hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.'], ['desde' => 'desde', 'hasta' => 'hasta']);

        $from = isset($data['desde']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['desde']) : CarbonImmutable::today()->startOfMonth();
        $to = isset($data['hasta']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['hasta']) : CarbonImmutable::today();

        return [$from ?: CarbonImmutable::today()->startOfMonth(), $to ?: CarbonImmutable::today()];
    }

    /** @return array<string, mixed> */
    private function data(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $queries = new ReportQueries($from, $to);
        $byMethod = $queries->incomeByMethod();

        return [
            'attended' => $queries->attended(),
            'top_procedures' => $queries->topProcedures(),
            'income_by_professional' => $queries->incomeByProfessional(),
            'income_by_method' => $byMethod,
            'income_total' => array_sum(array_column($byMethod, 'total')),
            'no_show' => $queries->noShowRate(),
            'without_control' => ReportQueries::withoutControl(6),
        ];
    }

    /** Evita inyección de fórmulas al abrir el CSV en una planilla. */
    private function safeCell(mixed $value): string|int
    {
        if (is_int($value)) {
            return $value;
        }

        $text = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;
    }
}
