<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Compra {{ $order->code }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 14px; color: #222; background: #f4f6f9; }
        .wrapper { max-width: 640px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a56db; padding: 28px 32px; text-align: center; }
        .header .company { font-size: 12px; color: rgba(255,255,255,.75); text-transform: uppercase; letter-spacing: 1px; }
        .header .title { font-size: 22px; font-weight: bold; color: #fff; margin-top: 6px; }
        .header .code { font-size: 15px; color: rgba(255,255,255,.85); margin-top: 4px; }
        .body { padding: 28px 32px; }
        .intro { font-size: 14px; color: #444; line-height: 1.6; margin-bottom: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
        .card-title { background: #f9fafb; padding: 10px 16px; font-size: 11px; font-weight: bold; color: #1a56db; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e5e7eb; }
        .row { display: flex; border-bottom: 1px solid #f3f4f6; }
        .row:last-child { border-bottom: none; }
        .label { width: 170px; min-width: 170px; padding: 9px 14px; font-size: 12px; font-weight: bold; color: #555; background: #fafafa; }
        .value { padding: 9px 14px; font-size: 12px; color: #222; flex: 1; }
        table.items { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.items th { background: #1a56db; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
        table.items td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; color: #333; }
        table.items td.right { text-align: right; }
        table.items tr:last-child td { border-bottom: none; }
        .totals { margin-top: 8px; text-align: right; font-size: 13px; color: #333; }
        .totals .total-line { padding: 3px 10px; }
        .totals .grand { font-weight: bold; font-size: 15px; color: #1a56db; }
        .attachment-note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px 16px; font-size: 13px; color: #1e40af; margin-bottom: 20px; }
        .attachment-note strong { display: block; margin-bottom: 4px; }
        .footer { padding: 18px 32px; background: #f9fafb; border-top: 1px solid #e5e7eb; text-align: center; font-size: 11px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="company">SGA — Centro Dermatológico Giovanni Bojanini</div>
        <div class="title">Orden de Compra</div>
        <div class="code">{{ $order->code }}</div>
    </div>

    <div class="body">
        <p class="intro">
            Estimado proveedor, adjunto encontrará la orden de compra oficial detallada a continuación.
            Por favor confirme recepción de este documento.
        </p>

        <div class="card">
            <div class="card-title">Información de la orden</div>
            <div class="row"><div class="label">N° Orden</div><div class="value">{{ $order->code }}</div></div>
            <div class="row"><div class="label">Fecha</div><div class="value">{{ $order->created_at->format('d/m/Y') }}</div></div>
            @if($order->expected_delivery_date)
            <div class="row"><div class="label">Entrega esperada</div><div class="value">{{ $order->expected_delivery_date->format('d/m/Y') }}</div></div>
            @endif
            @if($order->notes)
            <div class="row"><div class="label">Observaciones</div><div class="value">{{ $order->notes }}</div></div>
            @endif
        </div>

        <div class="card">
            <div class="card-title">Ítems solicitados ({{ $order->expenseItems->count() }})</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Descripción</th>
                        <th>Unidad</th>
                        <th style="text-align:right;">Cantidad</th>
                        <th style="text-align:right;">Precio unit.</th>
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
                        <td class="right">$ {{ number_format((float)$item->unit_price, 2) }}</td>
                        <td class="right">$ {{ number_format((float)$item->total_price, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;color:#999;padding:12px;">Sin ítems</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="totals">
                <div class="total-line">Subtotal: $ {{ number_format((float)$order->subtotal, 2) }}</div>
                @if((float)$order->tax_amount > 0)
                <div class="total-line">IVA: $ {{ number_format((float)$order->tax_amount, 2) }}</div>
                @endif
                <div class="total-line grand">Total: $ {{ number_format((float)$order->total_amount, 2) }}</div>
            </div>
        </div>

        <div class="attachment-note">
            <strong>Archivo adjunto</strong>
            La orden de compra completa se encuentra adjunta a este correo en formato PDF ({{ $order->code }}.pdf).
        </div>
    </div>

    <div class="footer">
        Este es un mensaje automático generado por el Sistema de Gestión (SGA).<br>
        Por favor no responda a este correo. &nbsp;·&nbsp; {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
