<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $prescription->type->label() }}</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #111; }
        .header { border-bottom: 2px solid #0e7c86; margin-bottom: 12px; width: 100%; border-collapse: collapse; }
        .header td { padding-bottom: 6px; vertical-align: bottom; }
        .clinic { font-size: 14pt; font-weight: bold; color: #0e7c86; }
        .muted { color: #555; font-size: 9pt; }
        h1 { font-size: 13pt; margin: 4px 0 10px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.data td { padding: 3px 0; vertical-align: top; }
        .label { color: #555; width: 28%; }
        .rx { font-size: 18pt; font-weight: bold; color: #0e7c86; }
        ol { padding-left: 18px; margin: 6px 0; }
        li { margin-bottom: 8px; }
        .drug { font-weight: bold; }
        .instructions { white-space: pre-line; margin-top: 8px; }
        .sign { margin-top: 40px; width: 55%; margin-left: 45%; border-top: 1px solid #333; text-align: center; padding-top: 4px; }
        .footer { margin-top: 18px; font-size: 8pt; color: #666; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <div class="clinic">{{ $clinic['name'] }}</div>
                <div class="muted">{{ collect([$clinic['address'], $clinic['phone']])->filter()->implode(' · ') }}</div>
            </td>
            <td style="text-align: right" class="muted">{{ $prescription->issued_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <h1>{{ $prescription->type->label() }}</h1>

    <table class="data">
        <tr><td class="label">Paciente</td><td>{{ $patient->full_name }}</td></tr>
        <tr><td class="label">Documento</td><td>{{ $patient->document_type->label() }} {{ $patient->document_number }}</td></tr>
        <tr><td class="label">Edad</td><td>{{ $patient->age }} años</td></tr>
    </table>

    @if ($prescription->type === \App\Enums\PrescriptionType::Prescription)
        <div class="rx">Rp/</div>
        <ol>
            @foreach ($prescription->medications ?? [] as $m)
                <li><span class="drug">{{ $m['drug'] }}</span> — {{ $m['dose'] }}<br>{{ $m['frequency'] }}, durante {{ $m['duration'] }}.</li>
            @endforeach
        </ol>
    @endif

    @if ($prescription->instructions)
        <div class="instructions">{{ $prescription->instructions }}</div>
    @endif

    <div class="sign">
        {{ $prescription->professional->name }}<br>
        <span class="muted">{{ $prescription->professional->license_number ? 'Reg. Prof. '.$prescription->professional->license_number : 'Odontólogo/a' }}</span>
    </div>

    <div class="footer">Documento N.º {{ $prescription->id }} emitido el {{ $prescription->issued_at->format('d/m/Y H:i') }}.</div>
</body>
</html>
