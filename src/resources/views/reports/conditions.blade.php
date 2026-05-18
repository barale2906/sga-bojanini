<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Condiciones de Almacenamiento</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #1a56db; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { font-size: 16px; margin: 0; color: #1a56db; }
        .header p { margin: 2px 0; color: #666; }
        .meta-table { width: 100%; margin-bottom: 15px; }
        .meta-table td { padding: 4px 8px; }
        .meta-table .label { font-weight: bold; width: 180px; background: #f3f4f6; }
        .stats-grid { display: table; width: 100%; margin-bottom: 15px; }
        .stats-box { display: table-cell; width: 25%; text-align: center; padding: 8px; border: 1px solid #e5e7eb; }
        .stats-box .value { font-size: 18px; font-weight: bold; color: #1a56db; }
        .stats-box .label { font-size: 9px; color: #666; }
        table.readings { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.readings th { background: #1a56db; color: #fff; padding: 6px; text-align: left; font-size: 10px; }
        table.readings td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        table.readings tr:nth-child(even) { background: #f9fafb; }
        .out-of-range { background: #fee2e2 !important; color: #991b1b; font-weight: bold; }
        .warning-range { background: #fef3c7 !important; color: #92400e; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 5px; }
        .capability { margin: 10px 0; padding: 8px; border-radius: 4px; }
        .capable { background: #d1fae5; color: #065f46; }
        .not-capable { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Centro Dermatológico Giovanni Bojanini</h1>
        <p><strong>Reporte de Condiciones de Almacenamiento</strong></p>
        <p>Generado: {{ $generated }}</p>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">Sensor:</td>
            <td>{{ $sensor->getCode() }} — {{ $sensor->getName() }}</td>
            <td class="label">Tipo:</td>
            <td>{{ $sensor->getType() === 'temperature' ? 'Temperatura' : 'Humedad' }}</td>
        </tr>
        <tr>
            <td class="label">Zona:</td>
            <td>{{ $zone ? $zone->getName() : 'N/A' }}</td>
            <td class="label">Periodo:</td>
            <td>{{ $dateFrom }} al {{ $dateTo }}</td>
        </tr>
        <tr>
            <td class="label">Rango permitido:</td>
            <td>{{ $zoneMin }} — {{ $zoneMax }} {{ $sensor->getUnit() }}</td>
            <td class="label">Total lecturas:</td>
            <td>{{ $chart['total_readings'] }}</td>
        </tr>
    </table>

    <div class="stats-grid">
        <div class="stats-box">
            <div class="value">{{ $chart['mean'] }}</div>
            <div class="label">Media ({{ $sensor->getUnit() }})</div>
        </div>
        <div class="stats-box">
            <div class="value">{{ $chart['std_dev'] }}</div>
            <div class="label">Desviación Estándar (σ)</div>
        </div>
        <div class="stats-box">
            <div class="value">{{ $chart['ucl'] }} / {{ $chart['lcl'] }}</div>
            <div class="label">UCL / LCL (±3σ)</div>
        </div>
        <div class="stats-box">
            <div class="value">{{ $chart['out_of_control_count'] }}</div>
            <div class="label">Fuera de Control</div>
        </div>
    </div>

    @if($chart['cp'] !== null)
    <div class="capability {{ $chart['process_capable'] ? 'capable' : 'not-capable' }}">
        <strong>Capacidad del Proceso:</strong>
        Cp = {{ $chart['cp'] }} | Cpk = {{ $chart['cpk'] }}
        — {{ $chart['process_capable'] ? '✓ Proceso capaz y centrado' : '✗ Proceso NO capaz o descentrado' }}
    </div>
    @endif

    <h3>Lecturas Registradas</h3>
    <table class="readings">
        <thead>
            <tr>
                <th>#</th>
                <th>Fecha/Hora</th>
                <th>Valor ({{ $sensor->getUnit() }})</th>
                <th>Fuente</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($readings as $index => $reading)
            @php
                $isOutOfRange = $reading->getValue() < $zoneMin || $reading->getValue() > $zoneMax;
                $isOutOfControl = $reading->getValue() > $chart['ucl'] || $reading->getValue() < $chart['lcl'];
                $rowClass = $isOutOfRange ? 'out-of-range' : ($isOutOfControl ? 'warning-range' : '');
            @endphp
            <tr class="{{ $rowClass }}">
                <td>{{ $index + 1 }}</td>
                <td>{{ $reading->getRecordedAt()->format('Y-m-d H:i') }}</td>
                <td>{{ number_format($reading->getValue(), 2) }}</td>
                <td>{{ $reading->getReadingSource() === 'manual' ? 'Manual' : 'IoT' }}</td>
                <td>
                    @if($isOutOfRange)
                        ⚠ FUERA DE RANGO
                    @elseif($isOutOfControl)
                        ⚡ FUERA DE CONTROL
                    @else
                        ✓ OK
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        SGA — Centro Dermatológico Giovanni Bojanini · Documento generado automáticamente · Página 1
    </div>
</body>
</html>
