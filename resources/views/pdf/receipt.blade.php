<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo N.º {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #111; }
        .header { border-bottom: 2px solid #0e7c86; margin-bottom: 14px; }
        .header-table td { padding-bottom: 8px; }
        .clinic { font-size: 15pt; font-weight: bold; color: #0e7c86; }
        .muted { color: #555; font-size: 9pt; }
        .header-table td { padding: 0; vertical-align: bottom; }
        .number { text-align: right; }
        .number strong { font-size: 13pt; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 0; vertical-align: top; }
        td.label { width: 32%; color: #555; }
        .amount { font-size: 16pt; font-weight: bold; }
        .words { font-style: italic; }
        .void { color: #b91c1c; border: 2px solid #b91c1c; padding: 6px; text-align: center; font-weight: bold; margin-bottom: 10px; }
        .footer { margin-top: 26px; font-size: 8.5pt; color: #666; border-top: 1px solid #ddd; padding-top: 6px; }
        .sign { margin-top: 34px; width: 45%; border-top: 1px solid #333; text-align: center; padding-top: 4px; float: right; }
    </style>
</head>
<body>
    {{-- Tabla en lugar de float: dompdf no calcula bien los float junto al borde. --}}
    <table class="header header-table">
        <tr>
            <td>
                <div class="clinic">{{ $clinic['name'] }}</div>
                <div class="muted">{{ collect([$clinic['address'], $clinic['phone']])->filter()->implode(' · ') }}</div>
            </td>
            <td class="number">
                <div class="muted">RECIBO</div>
                <strong>N.º {{ str_pad((string) $payment->receipt_number, 7, '0', STR_PAD_LEFT) }}</strong>
                <div class="muted">{{ $payment->paid_at->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    @if ($payment->voided_at)
        <div class="void">ANULADO el {{ $payment->voided_at->format('d/m/Y') }} — {{ $payment->void_reason }}</div>
    @endif

    <table>
        <tr><td class="label">Recibimos de</td><td>{{ $patient->full_name }} — {{ $patient->document_type->label() }} {{ $patient->document_number }}</td></tr>
        <tr><td class="label">La suma de</td><td><span class="amount">Gs. {{ number_format($payment->amount, 0, ',', '.') }}</span></td></tr>
        <tr><td class="label">Son</td><td class="words">guaraníes {{ $amountInWords }}</td></tr>
        <tr><td class="label">Concepto</td><td>{{ $payment->plan ? 'Pago a cuenta del plan «'.$payment->plan->title.'»' : 'Pago a cuenta de tratamientos odontológicos' }}{{ $payment->notes ? ' — '.$payment->notes : '' }}</td></tr>
        <tr><td class="label">Forma de pago</td><td>{{ $payment->method->label() }}{{ $payment->reference ? ' (operación '.$payment->reference.')' : '' }}</td></tr>
        <tr><td class="label">Saldo pendiente actual</td><td>Gs. {{ number_format(max($balance, 0), 0, ',', '.') }}{{ $balance < 0 ? ' (saldo a favor: Gs. '.number_format(-$balance, 0, ',', '.').')' : '' }}</td></tr>
    </table>

    <div class="sign">{{ $payment->receiver->name }}<br><span class="muted">Recibió</span></div>
    <div style="clear: both"></div>

    <div class="footer">
        Comprobante interno de pago. No reemplaza a la factura legal emitida conforme a la normativa de la SET.
    </div>
</body>
</html>
