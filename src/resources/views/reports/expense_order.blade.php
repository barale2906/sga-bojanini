<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden {{ $order->code }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #222; }
        .page { padding: 20px 24px; }
        .header { border-bottom: 2px solid #1a56db; padding-bottom: 12px; margin-bottom: 16px; }
        .header .company { font-size: 14px; font-weight: bold; color: #1a56db; }
        .header .subtitle { font-size: 11px; color: #555; margin-top: 2px; }
        .header .order-info { float: right; text-align: right; }
        .header .order-code { font-size: 16px; font-weight: bold; }
        .header .order-date { font-size: 11px; color: #555; }
        .clearfix::after { content: ''; display: table; clear: both; }
        .section { margin-bottom: 14px; }
        .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #1a56db; letter-spacing: .5px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin-bottom: 8px; }
        .info-grid { display: table; width: 100%; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 130px; font-weight: bold; color: #555; padding: 3px 0; font-size: 10px; }
        .info-value { display: table-cell; color: #222; padding: 3px 0; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 8px; }
        table th { background: #1a56db; color: #fff; padding: 5px 8px; text-align: left; }
        table td { padding: 5px 8px; border-bottom: 1px solid #f0f0f0; }
        table td.right { text-align: right; }
        .totals-box { text-align: right; padding-right: 8px; }
        .totals-box .t-line { padding: 2px 0; font-size: 11px; }
        .totals-box .t-grand { font-weight: bold; font-size: 13px; color: #1a56db; border-top: 1px solid #1a56db; padding-top: 4px; margin-top: 4px; }
        .signatures { margin-top: 32px; display: table; width: 100%; }
        .sig-box { display: table-cell; width: 45%; text-align: center; }
        .sig-line { border-top: 1px solid #555; padding-top: 6px; font-size: 10px; color: #555; margin-top: 40px; }
        .footer { margin-top: 20px; font-size: 9px; color: #aaa; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>
<div class="page">
    <div class="header clearfix">
        <div class="order-info">
            <div class="order-code">{{ $order->code }}</div>
            <div class="order-date">{{ $order->created_at->format('d/m/Y') }}</div>
        </div>
        <div class="company">Centro Dermatológico Giovanni Bojanini</div>
        <div class="subtitle">Orden de Compra — Gastos y Servicios</div>
    </div>

    <div class="section">
        <div class="section-title">Datos generales</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Proveedor</div>
                <div class="info-value">{{ $order->supplier?->name ?? '—' }}</div>
            </div>
            @if($order->expected_delivery_date)
            <div class="info-row">
                <div class="info-label">Entrega esperada</div>
                <div class="info-value">{{ $order->expected_delivery_date->format('d/m/Y') }}</div>
            </div>
            @endif
            @if($order->invoice_number)
            <div class="info-row">
                <div class="info-label">N° Factura</div>
                <div class="info-value">{{ $order->invoice_number }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha factura</div>
                <div class="info-value">{{ $order->invoice_date?->format('d/m/Y') ?? '—' }}</div>
            </div>
            @endif
            @if($order->notes)
            <div class="info-row">
                <div class="info-label">Observaciones</div>
                <div class="info-value">{{ $order->notes }}</div>
            </div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Ítems solicitados</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Descripción</th>
                    <th>Unidad</th>
                    <th style="text-align:right;">Cant. solicitada</th>
                    <th style="text-align:right;">Cant. recibida</th>
                    <th style="text-align:right;">Precio unit.</th>
                    <th style="text-align:right;">IVA %</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->expenseItems as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->unit }}</td>
                    <td class="right">{{ number_format((float)$item->quantity_requested, 2) }}</td>
                    <td class="right">{{ number_format((float)$item->quantity_received, 2) }}</td>
                    <td class="right">$ {{ number_format((float)$item->unit_price, 2) }}</td>
                    <td class="right">{{ $item->tax_rate }}%</td>
                    <td class="right">$ {{ number_format((float)$item->total_price, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:#999;">Sin ítems</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="totals-box">
            <div class="t-line">Subtotal: $ {{ number_format((float)$order->subtotal, 2) }}</div>
            @if((float)$order->tax_amount > 0)
            <div class="t-line">IVA: $ {{ number_format((float)$order->tax_amount, 2) }}</div>
            @endif
            <div class="t-grand">Total: $ {{ number_format((float)$order->total_amount, 2) }}</div>
        </div>
    </div>

    <div class="signatures">
        <div class="sig-box">
            <div class="sig-line">Elaboró: {{ $order->createdBy?->name ?? '—' }}</div>
        </div>
        <div class="sig-box" style="float:right;">
            <div class="sig-line">Proveedor / Autorizado</div>
        </div>
    </div>

    <div class="footer">
        Documento generado por SGA — {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
