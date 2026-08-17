<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante {{ $document->document_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 14px; color: #222; background: #f4f6f9; }
        .wrapper { max-width: 620px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a56db; padding: 28px 32px; text-align: center; }
        .header .company { font-size: 13px; color: rgba(255,255,255,.75); text-transform: uppercase; letter-spacing: 1px; }
        .header .doc-type { font-size: 22px; font-weight: bold; color: #fff; margin-top: 6px; }
        .header .doc-number { font-size: 15px; color: rgba(255,255,255,.85); margin-top: 4px; }
        .body { padding: 28px 32px; }
        .intro { font-size: 14px; color: #444; line-height: 1.6; margin-bottom: 24px; }
        .card { border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
        .card-title { background: #f9fafb; padding: 10px 16px; font-size: 11px; font-weight: bold; color: #1a56db; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e5e7eb; }
        .card-body { padding: 0; }
        .row { display: flex; border-bottom: 1px solid #f3f4f6; }
        .row:last-child { border-bottom: none; }
        .label { width: 160px; min-width: 160px; padding: 9px 14px; font-size: 12px; font-weight: bold; color: #555; background: #fafafa; }
        .value { padding: 9px 14px; font-size: 12px; color: #222; flex: 1; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .badge-confirmed { background: #d1fae5; color: #065f46; }
        table.items { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.items th { background: #1a56db; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
        table.items td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; color: #333; }
        table.items tr:last-child td { border-bottom: none; }
        .qty-neg { color: #b91c1c; font-weight: bold; }
        .qty-pos { color: #047857; font-weight: bold; }
        .attachment-note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 14px 16px; font-size: 13px; color: #1e40af; margin-bottom: 20px; }
        .attachment-note strong { display: block; margin-bottom: 4px; }
        .footer { padding: 18px 32px; background: #f9fafb; border-top: 1px solid #e5e7eb; text-align: center; font-size: 11px; color: #9ca3af; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="company">SGA — Centro Dermatológico Giovanni Bojanini</div>
        <div class="doc-type">{{ $typeLabel }}</div>
        <div class="doc-number">{{ $document->document_number }}</div>
    </div>

    <div class="body">
        <p class="intro">
            Se adjunta a este mensaje el comprobante oficial de la operación de inventario detallada a continuación.
        </p>

        <div class="card">
            <div class="card-title">Información del documento</div>
            <div class="card-body">
                <div class="row">
                    <div class="label">Tipo</div>
                    <div class="value">{{ $typeLabel }}</div>
                </div>
                <div class="row">
                    <div class="label">N° Comprobante</div>
                    <div class="value">{{ $document->document_number }}</div>
                </div>
                <div class="row">
                    <div class="label">Fecha</div>
                    <div class="value">{{ $document->movement_date?->format('d/m/Y') ?? $document->created_at->format('d/m/Y') }}</div>
                </div>
                <div class="row">
                    <div class="label">Estado</div>
                    <div class="value"><span class="badge badge-confirmed">Confirmado</span></div>
                </div>
                <div class="row">
                    <div class="label">Almacén origen</div>
                    <div class="value">{{ $document->warehouse?->name ?? '—' }}</div>
                </div>
                @if($document->warehouseTo)
                <div class="row">
                    <div class="label">Almacén destino</div>
                    <div class="value">{{ $document->warehouseTo->name }}</div>
                </div>
                @endif
                @if($document->costCenter)
                <div class="row">
                    <div class="label">Centro de costo</div>
                    <div class="value">{{ $document->costCenter->name }}</div>
                </div>
                @endif
                @if($document->medicalService)
                <div class="row">
                    <div class="label">Servicio médico</div>
                    <div class="value">{{ $document->medicalService->name }}</div>
                </div>
                @endif
                <div class="row">
                    <div class="label">Registrado por</div>
                    <div class="value">{{ $document->user?->name ?? '—' }}</div>
                </div>
                @if($document->reason)
                <div class="row">
                    <div class="label">Motivo</div>
                    <div class="value">{{ $document->reason }}</div>
                </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-title">Productos ({{ $document->movements->count() }} línea{{ $document->movements->count() !== 1 ? 's' : '' }})</div>
            <div class="card-body">
                <table class="items">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Producto</th>
                            <th>Lote</th>
                            <th>Vencimiento</th>
                            <th style="text-align:right;">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($document->movements as $i => $mov)
                        @php $qty = $mov->quantity; @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $mov->variant?->genericProduct?->name ?? '—' }}</td>
                            <td>{{ $mov->batch?->lot_number ?? '—' }}</td>
                            <td>{{ $mov->batch?->expiration_date ?? '—' }}</td>
                            <td style="text-align:right;" class="{{ $qty < 0 ? 'qty-neg' : 'qty-pos' }}">
                                {{ $qty > 0 ? '+' : '' }}{{ $qty }}
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#999;padding:12px;">Sin líneas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="attachment-note">
            <strong>Archivo adjunto</strong>
            El comprobante completo con firmas se encuentra adjunto a este correo en formato PDF
            (comprobante-{{ $document->document_number }}.pdf).
        </div>
    </div>

    <div class="footer">
        Este es un mensaje automático generado por el Sistema de Gestión de Almacén (SGA).<br>
        Por favor no responda a este correo. &nbsp;·&nbsp; {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
