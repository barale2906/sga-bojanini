<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de traslado {{ $transfer->id }}</title>
    @include('reports.assets._styles')
</head>
<body>
@include('reports.assets._header', [
    'title'    => 'Acta de ' . mb_strtolower($transfer->type->label()) . ' N° ' . str_pad((string) $transfer->id, 6, '0', STR_PAD_LEFT),
    'subtitle' => $transfer->status->label(),
])

<div class="section-title">Activo</div>
<table class="grid">
    <tr>
        <td class="label">Código</td><td>{{ $transfer->asset->code }}</td>
        <td class="label">Nombre</td><td>{{ $transfer->asset->name }}</td>
    </tr>
    <tr>
        <td class="label">Categoría</td><td>{{ $transfer->asset->category?->name }}</td>
        <td class="label">Serial</td><td>{{ $transfer->asset->serial ?? '—' }}</td>
    </tr>
</table>

<div class="section-title">Traslado</div>
<table class="grid">
    <tr>
        <td class="label">Origen</td><td>{{ $transfer->fromPlace?->fullName() ?? $transfer->from_external_location ?? '—' }}</td>
        <td class="label">Destino</td><td>{{ $transfer->toPlace?->fullName() ?? $transfer->external_destination ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">Responsable anterior</td><td>{{ $transfer->fromResponsible?->name ?? '—' }}</td>
        <td class="label">Nuevo responsable</td><td>{{ $transfer->toResponsible?->name ?? '—' }}</td>
    </tr>
    @if($transfer->supplier)
        <tr><td class="label">Proveedor</td><td colspan="3">{{ $transfer->supplier->name }}</td></tr>
    @endif
    @if($transfer->expected_return_date)
        <tr><td class="label">Retorno esperado</td><td colspan="3">{{ $transfer->expected_return_date->format('Y-m-d') }}</td></tr>
    @endif
    <tr><td class="label">Motivo</td><td colspan="3">{{ $transfer->reason ?? '—' }}</td></tr>
    <tr>
        <td class="label">Solicitado por</td><td>{{ $transfer->requester?->name }} — {{ $transfer->created_at?->format('Y-m-d H:i') }}</td>
        <td class="label">Confirmado</td><td>{{ $transfer->confirmed_at?->format('Y-m-d H:i') ?? '—' }}</td>
    </tr>
</table>

<div class="section-title">Firmas</div>
@include('reports.assets._signatures', [
    'signatures'  => $signatures,
    'pendingText' => $transfer->requires_signature ? 'Pendiente de firma' : 'Firma no requerida',
])

<div class="footer">Generado el {{ now()->format('Y-m-d H:i') }} — SGA Bojanini · Módulo de Activos</div>
</body>
</html>
