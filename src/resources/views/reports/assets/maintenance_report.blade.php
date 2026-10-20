<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mantenimiento {{ $maintenance->id }}</title>
    @include('reports.assets._styles')
</head>
<body>
@php $asset = $maintenance->asset; @endphp

@include('reports.assets._header', [
    'title'    => 'Reporte de mantenimiento N° ' . str_pad((string) $maintenance->id, 6, '0', STR_PAD_LEFT),
    'subtitle' => $maintenance->maintenance_type->label() . ' · ' . $maintenance->status->label(),
])

<div class="section-title">Equipo</div>
<table class="grid">
    <tr>
        <td class="label">Código</td><td>{{ $asset->code }}</td>
        <td class="label">Nombre</td><td>{{ $asset->name }}</td>
    </tr>
    <tr>
        <td class="label">Marca / Modelo</td><td>{{ $asset->brand ?? '—' }} / {{ $asset->model ?? '—' }}</td>
        <td class="label">Serial</td><td>{{ $asset->serial ?? '—' }}</td>
    </tr>
    <tr><td class="label">Ubicación</td><td colspan="3">{{ $asset->currentPlace?->fullName() ?? $asset->off_premises_location ?? '—' }}</td></tr>
</table>

<div class="section-title">Ejecución</div>
<table class="grid">
    <tr>
        <td class="label">Programado</td><td>{{ $maintenance->scheduled_date?->format('Y-m-d') }}</td>
        <td class="label">Ejecutado</td><td>{{ $maintenance->performed_at?->format('Y-m-d H:i') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Ejecutó</td>
        <td>
            @if($maintenance->performed_by_type?->value === 'external')
                {{ $maintenance->supplier?->name }} — {{ $maintenance->external_technician_name }}
            @else
                {{ $maintenance->technician?->name ?? '—' }}
            @endif
        </td>
        <td class="label">Registró</td><td>{{ $maintenance->registeredBy?->name ?? '—' }}</td>
    </tr>
    @if($maintenance->reported_failure)
        <tr><td class="label">Falla reportada</td><td colspan="3">{{ $maintenance->reported_failure }}</td></tr>
    @endif
    <tr><td class="label">Actividades</td><td colspan="3">{{ $maintenance->activities_performed ?? '—' }}</td></tr>
    @if($maintenance->findings)
        <tr><td class="label">Hallazgos</td><td colspan="3">{{ $maintenance->findings }}</td></tr>
    @endif
    @if($maintenance->recommendations)
        <tr><td class="label">Recomendaciones</td><td colspan="3">{{ $maintenance->recommendations }}</td></tr>
    @endif
    <tr>
        <td class="label">Resultado</td><td>{{ $maintenance->result?->label() ?? '—' }}</td>
        <td class="label">Costo</td><td>{{ $maintenance->cost !== null ? '$ ' . number_format((float) $maintenance->cost, 0, ',', '.') : '—' }}</td>
    </tr>
</table>

@if(! empty($maintenance->parts_replaced))
    <div class="section-title">Repuestos</div>
    <table class="lines">
        <thead><tr><th>Repuesto</th><th style="text-align:right;">Cantidad</th><th style="text-align:right;">Costo</th></tr></thead>
        <tbody>
        @foreach($maintenance->parts_replaced as $part)
            <tr>
                <td>{{ $part['name'] }}</td>
                <td style="text-align:right;">{{ $part['quantity'] }}</td>
                <td style="text-align:right;">{{ isset($part['cost']) ? '$ ' . number_format((float) $part['cost'], 0, ',', '.') : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($maintenance->documents->isNotEmpty())
    <div class="section-title">Soportes adjuntos</div>
    <table class="lines">
        <thead><tr><th>Tipo</th><th>Título</th><th>Archivo</th></tr></thead>
        <tbody>
        @foreach($maintenance->documents as $doc)
            <tr><td>{{ $doc->document_type->label() }}</td><td>{{ $doc->title }}</td><td>{{ $doc->original_name }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="section-title">Firmas</div>
@include('reports.assets._signatures', ['signatures' => $signatures, 'pendingText' => 'Sin firma'])

<div class="footer">Generado el {{ now()->format('Y-m-d H:i') }} — SGA Bojanini · Módulo de Activos</div>
</body>
</html>
