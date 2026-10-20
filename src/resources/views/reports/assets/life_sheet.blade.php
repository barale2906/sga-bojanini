<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de vida {{ $asset->code }}</title>
    @include('reports.assets._styles')
</head>
<body>
@php
    $profile = $asset->technicalProfile;
    $type    = $asset->category->asset_type;
    $isBio   = $type === \App\Modules\Assets\Domain\Enums\AssetType::Biomedical;
    $isPc    = $type === \App\Modules\Assets\Domain\Enums\AssetType::Computing;
    $enumLabel = fn ($enum, $value) => $value ? ($enum::tryFrom($value)?->label() ?? $value) : '—';
@endphp

@include('reports.assets._header', [
    'title'    => "Hoja de vida — {$asset->code}",
    'subtitle' => $asset->category->asset_type->label(),
    'barcode'  => $barcode,
])

<div class="section-title">Identificación</div>
<table class="grid">
    <tr><td class="label">Nombre</td><td colspan="3">{{ $asset->name }}</td></tr>
    <tr>
        <td class="label">Categoría</td><td>{{ $asset->category->name }}</td>
        <td class="label">Estado</td><td>{{ $asset->status->label() }}</td>
    </tr>
    <tr>
        <td class="label">Marca</td><td>{{ $asset->brand ?? '—' }}</td>
        <td class="label">Modelo</td><td>{{ $asset->model ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Serial</td><td>{{ $asset->serial ?? '—' }}</td>
        <td class="label">Responsable</td><td>{{ $asset->responsible?->name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Ubicación actual</td>
        <td colspan="3">{{ $asset->is_off_premises ? 'Fuera de las instalaciones: ' . $asset->off_premises_location : ($asset->currentPlace?->fullName() ?? '—') }}</td>
    </tr>
    @if($asset->description)
        <tr><td class="label">Descripción</td><td colspan="3">{{ $asset->description }}</td></tr>
    @endif
</table>

<div class="section-title">Adquisición</div>
<table class="grid">
    <tr>
        <td class="label">Fecha</td><td>{{ $asset->acquisition_date?->format('Y-m-d') ?? '—' }}</td>
        <td class="label">Valor</td><td>{{ $asset->acquisition_value !== null ? '$ ' . number_format((float) $asset->acquisition_value, 0, ',', '.') : '—' }}</td>
    </tr>
    <tr>
        <td class="label">Proveedor</td><td>{{ $asset->supplier?->name ?? '—' }}</td>
        <td class="label">Factura</td><td>{{ $asset->invoice_number ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Garantía hasta</td><td>{{ $asset->warranty_until?->format('Y-m-d') ?? '—' }}</td>
        <td class="label">Forma</td><td>{{ $enumLabel(\App\Modules\Assets\Domain\Enums\AcquisitionMode::class, $profile?->acquisition_mode) }}</td>
    </tr>
</table>

@if($type->requiresMaintenance())
    <div class="section-title">Datos técnicos</div>
    <table class="grid">
        <tr>
            <td class="label">Fabricante</td><td>{{ $profile?->manufacturer ?? '—' }}</td>
            <td class="label">País de origen</td><td>{{ $profile?->country_of_origin ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Año fabricación</td><td>{{ $profile?->manufacture_year ?? '—' }}</td>
            <td class="label">Vida útil</td><td>{{ $profile?->useful_life_years ? $profile->useful_life_years . ' años' : '—' }}</td>
        </tr>
        @if($isBio)
            <tr>
                <td class="label">Registro INVIMA</td><td>{{ $profile?->invima_registry ?? '—' }}</td>
                <td class="label">Clase de riesgo</td><td>{{ $enumLabel(\App\Modules\Assets\Domain\Enums\RiskClass::class, $profile?->risk_class) }}</td>
            </tr>
            <tr>
                <td class="label">Clasificación</td><td>{{ $enumLabel(\App\Modules\Assets\Domain\Enums\BiomedicalClass::class, $profile?->biomedical_class) }}</td>
                <td class="label">Fuente de energía</td><td>{{ $enumLabel(\App\Modules\Assets\Domain\Enums\PowerSource::class, $profile?->power_source) }}</td>
            </tr>
            <tr>
                <td class="label">Voltaje / Corriente</td><td>{{ $profile?->voltage ?? '—' }} / {{ $profile?->current ?? '—' }}</td>
                <td class="label">Potencia / Frecuencia</td><td>{{ $profile?->power ?? '—' }} / {{ $profile?->frequency ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Requiere calibración</td><td>{{ $profile?->requires_calibration ? 'Sí' : 'No' }}</td>
                <td class="label">Condiciones de operación</td><td>{{ $profile?->operating_conditions ?? '—' }}</td>
            </tr>
        @endif
        @if($isPc)
            <tr>
                <td class="label">Procesador</td><td>{{ $profile?->processor ?? '—' }}</td>
                <td class="label">RAM / Almacenamiento</td><td>{{ $profile?->ram ?? '—' }} / {{ $profile?->storage ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Sistema operativo</td><td>{{ $profile?->operating_system ?? '—' }}</td>
                <td class="label">Nombre de equipo</td><td>{{ $profile?->hostname ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">IP</td><td>{{ $profile?->ip_address ?? '—' }}</td>
                <td class="label">MAC</td><td>{{ $profile?->mac_address ?? '—' }}</td>
            </tr>
            @if(! empty($profile?->licenses))
                <tr>
                    <td class="label">Licencias</td>
                    <td colspan="3">{{ collect($profile->licenses)->map(fn ($l) => $l['name'] . (! empty($l['expires_at']) ? ' (vence ' . $l['expires_at'] . ')' : ''))->implode(', ') }}</td>
                </tr>
            @endif
        @endif
        @if(! empty($profile?->technical_specs))
            @foreach($profile->technical_specs as $key => $value)
                <tr><td class="label">{{ $key }}</td><td colspan="3">{{ $value }}</td></tr>
            @endforeach
        @endif
        @if($profile?->accessories)
            <tr><td class="label">Accesorios</td><td colspan="3">{{ $profile->accessories }}</td></tr>
        @endif
        @if($profile?->usage_recommendations)
            <tr><td class="label">Recomendaciones de uso</td><td colspan="3">{{ $profile->usage_recommendations }}</td></tr>
        @endif
    </table>

    <div class="section-title">Planes de mantenimiento</div>
    <table class="lines">
        <thead><tr><th>Tipo</th><th>Frecuencia</th><th>Ejecuta</th><th>Próxima fecha</th></tr></thead>
        <tbody>
        @forelse($asset->maintenancePlans as $plan)
            <tr>
                <td>{{ $plan->maintenance_type->label() }}</td>
                <td>Cada {{ $plan->frequency_value }} {{ mb_strtolower($plan->frequency_unit->label()) }}</td>
                <td>{{ $plan->performed_by_type->label() }}{{ $plan->technician ? ' — ' . $plan->technician->name : '' }}{{ $plan->supplier ? ' — ' . $plan->supplier->name : '' }}</td>
                <td>{{ $plan->openOrder?->scheduled_date?->format('Y-m-d') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">Sin planes activos</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">Historial de mantenimientos</div>
    <table class="lines">
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Actividades</th><th>Ejecutó</th><th>Resultado</th><th>Registró</th></tr></thead>
        <tbody>
        @forelse($asset->maintenances as $m)
            <tr>
                <td>{{ $m->performed_at?->format('Y-m-d') }}</td>
                <td>{{ $m->maintenance_type->label() }}</td>
                <td>{{ \Illuminate\Support\Str::limit($m->activities_performed, 160) }}</td>
                <td>{{ $m->performed_by_type?->value === 'external' ? trim(($m->supplier?->name ?? '') . ' — ' . ($m->external_technician_name ?? ''), ' —') : $m->technician?->name }}</td>
                <td>{{ $m->result?->label() }}</td>
                <td>{{ $m->registeredBy?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Sin mantenimientos registrados</td></tr>
        @endforelse
        </tbody>
    </table>
@endif

<div class="section-title">Historial de ubicaciones</div>
<table class="lines">
    <thead><tr><th>Desde</th><th>Hasta</th><th>Ubicación</th><th>Motivo</th></tr></thead>
    <tbody>
    @foreach($asset->locationHistory as $segment)
        <tr>
            <td>{{ $segment->from_date?->format('Y-m-d H:i') }}</td>
            <td>{{ $segment->to_date?->format('Y-m-d H:i') ?? 'Actual' }}</td>
            <td>{{ $segment->place?->fullName() ?? $segment->external_location }}</td>
            <td>{{ $segment->transfer?->type->label() ?? 'Registro inicial' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="section-title">Documentos</div>
<table class="lines">
    <thead><tr><th>Tipo</th><th>Título</th><th>Emisión</th><th>Vencimiento</th></tr></thead>
    <tbody>
    @forelse($asset->documents as $doc)
        <tr>
            <td>{{ $doc->document_type->label() }}</td>
            <td>{{ $doc->title }}</td>
            <td>{{ $doc->issued_at?->format('Y-m-d') ?? '—' }}</td>
            <td>{{ $doc->expires_at?->format('Y-m-d') ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">Sin documentos</td></tr>
    @endforelse
    </tbody>
</table>

@if($asset->comments->isNotEmpty())
    <div class="section-title">Comentarios</div>
    <table class="lines">
        <thead><tr><th style="width:18%;">Fecha</th><th style="width:20%;">Autor</th><th>Comentario</th></tr></thead>
        <tbody>
        @foreach($asset->comments as $comment)
            <tr>
                <td>{{ $comment->created_at?->format('Y-m-d H:i') }}</td>
                <td>{{ $comment->user?->name }}</td>
                <td>{{ $comment->comment }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="footer">Generado el {{ now()->format('Y-m-d H:i') }} — SGA Bojanini · Módulo de Activos</div>
</body>
</html>
