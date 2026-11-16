<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Export;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorReadingModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lecturas de un equipo de monitoreo en un periodo, con encabezado del
 * equipo, resumen estadístico (SPC) y el detalle de cada lectura.
 */
class SensorReadingsExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    private const STATUS_LABELS = ['ok' => 'Normal', 'warning' => 'Advertencia', 'alarm' => 'Alarma'];

    private int $tableHeaderRow = 0;

    /**
     * @param array{name: string, code: string, type: string, unit: string} $sensor
     * @param array|null $zone  Zona con almacén y límites (SensorOverviewQuery)
     * @param Collection<int, SensorReadingModel> $readings
     * @param array $stats      Resultado de GetControlChartUseCase
     */
    public function __construct(
        private readonly array $sensor,
        private readonly ?array $zone,
        private readonly Collection $readings,
        private readonly array $stats,
        private readonly string $dateFrom,
        private readonly string $dateTo,
    ) {}

    public function title(): string
    {
        return 'Lecturas';
    }

    public function array(): array
    {
        [$min, $max] = $this->limits();
        $unit = $this->sensor['unit'];
        $hasStats = ($this->stats['total_readings'] ?? 0) >= 2;
        $spcStatus = collect($this->stats['readings'] ?? [])->pluck('status', 'id');

        $rows = [
            ['Registro de condiciones ambientales'],
            [],
            ['Equipo', "{$this->sensor['name']} ({$this->sensor['code']})"],
            ['Tipo', $this->sensor['type'] === 'temperature' ? 'Temperatura' : 'Humedad'],
            ['Almacén', $this->zone['warehouse_name'] ?? '—'],
            ['Zona', $this->zone['name'] ?? '—'],
            ['Rango permitido', $min !== null && $max !== null ? "{$min} – {$max} {$unit}" : 'Sin definir'],
            ['Periodo', "{$this->dateFrom} a {$this->dateTo}"],
            ['Generado', now()->format('Y-m-d H:i')],
            [],
            ['Resumen estadístico'],
            ['Total lecturas', $this->readings->count()],
            ['Fuera del rango permitido', $this->readings->filter(fn ($r) => $this->outOfRange((float) $r->value, $min, $max))->count()],
            ['Mínimo', $this->readings->isEmpty() ? null : (float) $this->readings->min('value')],
            ['Máximo', $this->readings->isEmpty() ? null : (float) $this->readings->max('value')],
            ['Promedio (X̄)', $this->readings->isEmpty() ? null : round((float) $this->readings->avg('value'), 2)],
            ['Desviación estándar (σ)', $hasStats ? $this->stats['std_dev'] : null],
            ['LCS / LCI', $hasStats ? "{$this->stats['ucl']} / {$this->stats['lcl']}" : null],
            ['Cp / Cpk', $hasStats ? (($this->stats['cp'] ?? '—') . ' / ' . ($this->stats['cpk'] ?? '—')) : null],
            ['Puntos fuera de control', $hasStats ? $this->stats['out_of_control_count'] : null],
            [],
        ];

        $rows[] = ['Fecha', 'Hora', "Valor ({$unit})", 'Rango permitido', 'Control SPC', 'Origen', 'Registrado por', 'Fecha de registro'];
        $this->tableHeaderRow = count($rows);

        foreach ($this->readings as $reading) {
            $value = (float) $reading->value;
            $status = $spcStatus->get($reading->id);

            $rows[] = [
                $reading->recorded_at->format('Y-m-d'),
                $reading->recorded_at->format('H:i'),
                $value,
                $min === null || $max === null ? '—' : ($this->outOfRange($value, $min, $max) ? 'Fuera de rango' : 'Dentro'),
                $status ? self::STATUS_LABELS[$status] ?? $status : '—',
                $reading->reading_source === 'iot' ? 'Automático (IoT)' : 'Manual',
                $reading->user?->name ?? '—',
                $reading->created_at?->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1  => ['font' => ['bold' => true, 'size' => 14]],
            11 => ['font' => ['bold' => true, 'size' => 12]],
            'A3:A20' => ['font' => ['bold' => true]],
            $this->tableHeaderRow => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2D3748']],
            ],
        ];
    }

    /** @return array{0: ?float, 1: ?float} */
    private function limits(): array
    {
        if ($this->zone === null) {
            return [null, null];
        }

        return $this->sensor['type'] === 'temperature'
            ? [$this->zone['temp_min'], $this->zone['temp_max']]
            : [$this->zone['humidity_min'], $this->zone['humidity_max']];
    }

    private function outOfRange(float $value, ?float $min, ?float $max): bool
    {
        return ($min !== null && $value < $min) || ($max !== null && $value > $max);
    }
}
