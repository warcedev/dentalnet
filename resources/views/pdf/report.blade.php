<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; }
        h1 { font-size: 15pt; color: #0e7c86; margin: 0; }
        h2 { font-size: 11pt; margin: 18px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #eee; }
        th { background: #f3f4f6; font-weight: bold; }
        td.num, th.num { text-align: right; }
        .kpis td { border: 1px solid #ddd; text-align: center; padding: 8px; }
        .kpi { font-size: 14pt; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $clinic }} — Reporte de gestión</h1>
    <p class="muted">Período: {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }} · Generado el {{ now()->format('d/m/Y H:i') }}</p>

    <table class="kpis">
        <tr>
            <td><div class="kpi">{{ $attended['patients'] }}</div>pacientes atendidos</td>
            <td><div class="kpi">{{ $attended['consultations'] }}</div>consultas</td>
            <td><div class="kpi">Gs. {{ number_format($income_total, 0, ',', '.') }}</div>ingresos</td>
            <td><div class="kpi">{{ number_format($no_show['rate'], 1, ',', '.') }} %</div>inasistencias ({{ $no_show['no_show'] }} de {{ $no_show['no_show'] + $no_show['attended'] }})</td>
        </tr>
    </table>

    <h2>Tratamientos más frecuentes</h2>
    <table>
        <tr><th>Procedimiento</th><th class="num">Cantidad</th></tr>
        @forelse ($top_procedures as $r)
            <tr><td>{{ $r['procedure'] }}</td><td class="num">{{ $r['count'] }}</td></tr>
        @empty
            <tr><td colspan="2" class="muted">Sin procedimientos en el período.</td></tr>
        @endforelse
    </table>

    <h2>Ingresos por profesional</h2>
    <table>
        <tr><th>Profesional</th><th class="num">Pagos</th><th class="num">Total</th></tr>
        @forelse ($income_by_professional as $r)
            <tr><td>{{ $r['professional'] }}</td><td class="num">{{ $r['payments'] }}</td><td class="num">Gs. {{ number_format($r['total'], 0, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">Sin pagos en el período.</td></tr>
        @endforelse
    </table>

    <h2>Ingresos por forma de pago</h2>
    <table>
        <tr><th>Forma de pago</th><th class="num">Pagos</th><th class="num">Total</th></tr>
        @forelse ($income_by_method as $r)
            <tr><td>{{ $r['label'] }}</td><td class="num">{{ $r['payments'] }}</td><td class="num">Gs. {{ number_format($r['total'], 0, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">Sin pagos en el período.</td></tr>
        @endforelse
    </table>

    <h2>Pacientes sin control hace más de 6 meses ({{ count($without_control) }})</h2>
    <table>
        <tr><th>Paciente</th><th>Teléfono</th><th>Última visita</th></tr>
        @foreach (array_slice($without_control, 0, 60) as $r)
            <tr><td>{{ $r['name'] }}</td><td>{{ $r['phone'] ?? '—' }}</td><td>{{ $r['last_visit'] ? \Carbon\CarbonImmutable::parse($r['last_visit'])->format('d/m/Y') : 'Nunca' }}</td></tr>
        @endforeach
    </table>
    @if (count($without_control) > 60)
        <p class="muted">Se muestran 60 de {{ count($without_control) }}. Exporte el CSV para la lista completa.</p>
    @endif
</body>
</html>
