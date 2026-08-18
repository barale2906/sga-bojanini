<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OC Gastos {{ $order->code }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 14px; color: #222; background: #f4f6f9; }
        .wrapper { max-width: 640px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #065f46; padding: 28px 32px; text-align: center; }
        .header .company { font-size: 12px; color: rgba(255,255,255,.75); text-transform: uppercase; letter-spacing: 1px; }
        .header .title { font-size: 22px; font-weight: bold; color: #fff; margin-top: 6px; }
        .header .code { font-size: 15px; color: rgba(255,255,255,.85); margin-top: 4px; }
        .body { padding: 28px 32px; }
        .intro { font-size: 14px; color: #444; line-height: 1.6; margin-bottom: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
        .card-title { background: #f9fafb; padding: 10px 16px; font-size: 11px; font-weight: bold; color: #065f46; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e5e7eb; }
        .row { display: flex; border-bottom: 1px solid #f3f4f6; }
        .row:last-child { border-bottom: none; }
        .label { width: 170px; min-width: 170px; padding: 9px 14px; font-size: 12px; font-weight: bold; color: #555; background: #fafafa; }
        .value { padding: 9px 14px; font-size: 12px; color: #222; flex: 1; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-unpaid { background: #fee2e2; color: #991b1b; }
        table.items { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.items th { background: #065f46; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
        table.items td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; color: #333; }
        table.items td.right { text-align: right; }
        table.items tr:last-child td { border-bottom: none; }
        .totals { margin-top: 8px; text-align: right; font-size: 13px; color: #333; padding: 8px 10px; }
        .totals .total-line { padding: 3px 0; }
        .totals .grand { font-weight: bold; font-size: 15px; color: #065f46; }
        .attachment-note { background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 6px; padding: 14px 16px; font-size: 13px; color: #065f46; margin-bottom: 20px; }
        .attachment-note strong { display: block; margin-bottom: 4px; }
        .footer { padding: 18px 32px; background: #f9fafb; border-top: 1px solid #e5e7eb; text-align: center; font-size: 11px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="company">SGA — Centro Dermatológico Giovanni Bojanini</div>
        <div class="title">OC Gastos — Para Contabilidad</div>
        <div class="code">{{ $order->code }}</div>
    </div>

    <div class="body">
        <p class="intro">
            Se remite a contabilidad la siguiente orden de compra de gastos con su respectiva factura para tramitar el pago.
        </p>

        <div class="card">
            <div class="card-title">Datos de la orden</div>
            <div class="row"><div class="label">N° Orden</div><div class="value">{{ $order->code }}</div></div>
            <div class="row"><div class="label">Proveedor</div><div class="value">{{ $order->supplier?->name ?? '—' }}</div></div>
            <div class="row"><div class="label">Fecha orden</div><div class="value">{{ $order->created_at->format('d/m/Y') }}</div></div>
            <div class="row"><div class="label">Registrado por</div><div class="value">{{ $order->createdBy?->name ?? '—' }}</div></div>
            @if($order->notes)
            <div class="row"><div class="label">Observaciones</div><div class="value">{{ $order->notes }}</div></div>
            @endif
        </div>

        <div class="card">
            <div class="card-title">Datos de factura</div>
            <div class="row"><div class="label">N° Factura</div><div class="value"><strong>{{ $order->invoice_number }}</strong></div></div>
            <div class="row"><div class="label">Fecha factura</div><div class="value">{{ $order->invoice_date?->format('d/m/Y') ?? '—' }}</div></div>
        </div>

        <div class="card">
            <div class="card-title">Estado de pago</div>
            @php
                $badgeClass = match($order->payment_status) {
                    'paid'    => 'badge-paid',
                    'partial' => 'badge-partial',
                    default   => 'badge-unpaid',
                };
                $badgeLabel = match($order->payment_status) {
                    'paid'    => 'Pagado',
                    'partial' => 'Pago parcial',
                    default   => 'Sin pago',
                };
            @endphp
            <div class="row"><div class="label">Estado</div><div class="value"><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></div></div>
            <div class="row"><div class="label">Total orden</div><div class="value">$ {{ number_format((float)$order->total_amount, 2) }}</div></div>
            <div class="row"><div class="label">Pagado</div><div class="value">$ {{ number_format((float)$order->amount_paid, 2) }}</div></div>
            <div class="row"><div class="label">Saldo pendiente</div><div class="value"><strong>$ {{ number_format((float)$order->total_amount - (float)$order->amount_paid, 2) }}</strong></div></div>
        </div>

        @if($order->payments->isNotEmpty())
        <div class="card">
            <div class="card-title">Pagos registrados</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th style="text-align:right;">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->payments as $payment)
                    @php
                        $method = match($payment->payment_method) {
                            'cash'     => 'Efectivo',
                            'transfer' => 'Transferencia',
                            'check'    => 'Cheque',
                            default    => 'Otro',
                        };
                    @endphp
                    <tr>
                        <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                        <td>{{ $method }}</td>
                        <td>{{ $payment->reference ?? '—' }}</td>
                        <td class="right">$ {{ number_format((float)$payment->amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="card">
            <div class="card-title">Ítems de la orden ({{ $order->expenseItems->count() }})</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Descripción</th>
                        <th>Unidad</th>
                        <th style="text-align:right;">Cant.</th>
                        <th style="text-align:right;">P. Unit.</th>
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
            La orden de compra completa se encuentra adjunta en formato PDF ({{ $order->code }}.pdf).
        </div>
    </div>

    <div class="footer">
        Este es un mensaje automático generado por el Sistema de Gestión (SGA).<br>
        Por favor no responda a este correo. &nbsp;·&nbsp; {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
