<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante {{ $document->document_number }}</title>
    <style>
        @page { margin: 15mm 15mm 15mm 15mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #222; margin: 15mm 15mm 15mm 15mm; }

        .header { text-align: center; border-bottom: 2px solid #1a56db; padding-bottom: 8px; margin-bottom: 12px; }
        .header .company { font-size: 13px; font-weight: bold; color: #1a56db; }
        .header .doc-number { font-size: 18px; font-weight: bold; margin: 4px 0; }
        .header .doc-type { font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: 1px; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-pending { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
        .badge-confirmed { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }

        .meta-grid { display: table; width: 100%; margin-bottom: 12px; border: 1px solid #e5e7eb; }
        .meta-row { display: table-row; }
        .meta-cell { display: table-cell; padding: 4px 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .meta-cell.label { font-weight: bold; background: #f9fafb; width: 130px; color: #444; }
        .meta-cell.half { width: 50%; }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #1a56db;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 12px 0 4px 0;
            padding-bottom: 2px;
            border-bottom: 1px solid #e5e7eb;
        }

        table.lines { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.lines th { background: #1a56db; color: #fff; padding: 5px 6px; text-align: left; font-size: 9px; }
        table.lines td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; font-size: 9px; vertical-align: top; }
        table.lines tr:nth-child(even) td { background: #f9fafb; }
        .qty-neg { color: #b91c1c; font-weight: bold; }
        .qty-pos { color: #047857; font-weight: bold; }

        .signatures { display: table; width: 100%; margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .sig-box { display: table-cell; width: 50%; padding: 0 12px; text-align: center; vertical-align: top; }
        .sig-label { font-weight: bold; font-size: 9px; color: #555; margin-bottom: 4px; text-transform: uppercase; }
        .sig-img { max-width: 180px; max-height: 60px; border: 1px solid #e5e7eb; padding: 2px; }
        .sig-name { margin-top: 4px; font-size: 9px; border-top: 1px solid #aaa; padding-top: 3px; }
        .sig-pending { color: #92400e; font-style: italic; font-size: 9px; margin-top: 20px; }

        .footer { margin-top: 16px; text-align: center; font-size: 8px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 5px; }
    </style>
</head>
<body>

@php
    $typeLabels = [
        'exit'                 => 'Salida de Inventario',
        'entry'                => 'Entrada de Inventario',
        'transfer'             => 'Traslado entre Almacenes',
        'adjustment'           => 'Ajuste de Inventario',
        'return'               => 'Devolución a Proveedor',
        'expiration_write_off' => 'Baja por Vencimiento',
        'loss'                 => 'Baja / Pérdida',
    ];
    $typeLabel = $typeLabels[$document->document_type] ?? $document->document_type;
    $status    = $document->status instanceof \App\Modules\Inventory\Domain\ValueObjects\MovementStatus
        ? $document->status->value
        : $document->status;
@endphp

{{-- Encabezado --}}
<div class="header">
    <div class="company">Centro Dermatológico Giovanni Bojanini</div>
    <div class="doc-number">{{ $document->document_number }}</div>
    <div class="doc-type">{{ $typeLabel }}</div>
    <div style="margin-top:4px;">
        <span class="badge {{ $status === 'confirmed' ? 'badge-confirmed' : 'badge-pending' }}">
            {{ $status === 'confirmed' ? 'Confirmado' : 'Pendiente de firma' }}
        </span>
    </div>
</div>

{{-- Información general --}}
<div class="section-title">Información del Comprobante</div>
<div class="meta-grid">
    <div class="meta-row">
        <div class="meta-cell label">Fecha:</div>
        <div class="meta-cell">{{ $document->created_at->format('Y-m-d H:i:s') }}</div>
        <div class="meta-cell label">Registrado por:</div>
        <div class="meta-cell">{{ $document->user?->name ?? '-' }}</div>
    </div>
    <div class="meta-row">
        <div class="meta-cell label">Almacén origen:</div>
        <div class="meta-cell">{{ $document->warehouse?->name ?? '-' }}</div>
        @if($document->warehouse_to_id)
        <div class="meta-cell label">Almacén destino:</div>
        <div class="meta-cell">{{ $document->warehouseTo?->name ?? '-' }}</div>
        @else
        <div class="meta-cell label"></div>
        <div class="meta-cell"></div>
        @endif
    </div>
    @if($document->reason)
    <div class="meta-row">
        <div class="meta-cell label">Observaciones:</div>
        <div class="meta-cell" colspan="3">{{ $document->reason }}</div>
    </div>
    @endif
</div>

{{-- Centro de costo / paciente --}}
@if($document->cost_center_id)
<div class="section-title">Centro de Costo</div>
<div class="meta-grid">
    <div class="meta-row">
        <div class="meta-cell label">Centro de costo:</div>
        <div class="meta-cell">{{ $document->costCenter?->name ?? '-' }} ({{ $document->costCenter?->code ?? '' }})</div>
        <div class="meta-cell label">Tipo:</div>
        <div class="meta-cell">{{ $document->costCenter?->type === 'external' ? 'Externo (Paciente)' : 'Interno' }}</div>
    </div>
    @if($document->service_id)
    <div class="meta-row">
        <div class="meta-cell label">Servicio médico:</div>
        <div class="meta-cell">{{ $document->medicalService?->name ?? '-' }}</div>
        <div class="meta-cell label"></div>
        <div class="meta-cell"></div>
    </div>
    @endif
    @if($document->patient_document)
    <div class="meta-row">
        <div class="meta-cell label">Doc. paciente:</div>
        <div class="meta-cell">{{ $document->patient_document }}</div>
        <div class="meta-cell label">ID externo:</div>
        <div class="meta-cell">{{ $document->patient_external_id ?? '-' }}</div>
    </div>
    @endif
</div>
@endif

{{-- Entrada: factura / temperatura --}}
@if($document->invoice_number || $document->entry_temperature !== null)
<div class="section-title">Datos de Entrada</div>
<div class="meta-grid">
    <div class="meta-row">
        @if($document->invoice_number)
        <div class="meta-cell label">N° Factura:</div>
        <div class="meta-cell">{{ $document->invoice_number }}</div>
        @endif
        @if($document->entry_temperature !== null)
        <div class="meta-cell label">Temperatura entrada:</div>
        <div class="meta-cell">{{ $document->entry_temperature }} °C</div>
        @endif
    </div>
</div>
@endif

{{-- Líneas de producto --}}
<div class="section-title">Productos</div>
<table class="lines">
    <thead>
        <tr>
            <th>#</th>
            <th>Producto</th>
            <th>Variante / Marca</th>
            <th>Lote</th>
            <th>Vencimiento</th>
            <th>Ubicación</th>
            <th style="text-align:right;">Cantidad</th>
        </tr>
    </thead>
    <tbody>
        @forelse($document->movements as $i => $mov)
        @php
            $loc = $mov->locationFrom?->name ?? $mov->locationTo?->name ?? '-';
            $qty = $mov->quantity;
        @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $mov->variant?->genericProduct?->name ?? '-' }}</td>
            <td>{{ $mov->variant?->lab_brand ?? '-' }}</td>
            <td>{{ $mov->batch?->lot_number ?? '-' }}</td>
            <td>{{ $mov->batch?->expiration_date ?? '-' }}</td>
            <td>{{ $loc }}</td>
            <td style="text-align:right;" class="{{ $qty < 0 ? 'qty-neg' : 'qty-pos' }}">
                {{ $qty > 0 ? '+' : '' }}{{ $qty }}
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:#999;">Sin líneas registradas</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Firmas --}}
<div class="section-title">Firmas</div>
<div class="signatures">
    @php
        $delivered = $document->signatures->firstWhere('role', 'delivered_by');
        $received  = $document->signatures->firstWhere('role', 'received_by');
    @endphp

    <div class="sig-box">
        <div class="sig-label">Entregado por</div>
        @if($delivered)
            @if($delivered->signature_data)
                <img class="sig-img" src="{{ $delivered->signature_data }}" alt="Firma entregado por">
            @endif
            <div class="sig-name">
                {{ $delivered->signer_name }}<br>
                Doc: {{ $delivered->signer_document }}<br>
                <span style="color:#666;">{{ \Carbon\Carbon::parse($delivered->signed_at)->format('Y-m-d H:i') }}</span>
            </div>
        @else
            <div class="sig-pending">Pendiente de firma</div>
        @endif
    </div>

    <div class="sig-box">
        <div class="sig-label">Recibido por</div>
        @if($received)
            @if($received->signature_data)
                <img class="sig-img" src="{{ $received->signature_data }}" alt="Firma recibido por">
            @endif
            <div class="sig-name">
                {{ $received->signer_name }}<br>
                Doc: {{ $received->signer_document }}<br>
                <span style="color:#666;">{{ \Carbon\Carbon::parse($received->signed_at)->format('Y-m-d H:i') }}</span>
            </div>
        @else
            <div class="sig-pending">Pendiente de firma</div>
        @endif
    </div>
</div>

<div class="footer">
    SGA — Centro Dermatológico Giovanni Bojanini &nbsp;·&nbsp;
    Generado: {{ now()->format('Y-m-d H:i:s') }} &nbsp;·&nbsp;
    Documento: {{ $document->document_number }}
</div>

</body>
</html>
