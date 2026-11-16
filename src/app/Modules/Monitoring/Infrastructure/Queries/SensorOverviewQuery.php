<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Queries;

use App\Modules\Monitoring\Domain\Services\CalibrationStatusService;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorCalibrationCertificateModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorReadingModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Support\Facades\DB;

/**
 * Datos complementarios de los sensores para el listado y el detalle:
 * zona con su almacén y condiciones, certificado de calibración
 * vigente y último registro. Todo en consultas por lote (sin N+1).
 */
class SensorOverviewQuery
{
    public function __construct(
        private readonly CalibrationStatusService $calibrationStatus,
    ) {}

    /**
     * @param array<int, array{id: int, zone_id: int}> $sensors
     * @return array<int, array{zone: ?array, calibration: array, last_reading: ?array}>
     */
    public function forSensors(array $sensors): array
    {
        if ($sensors === []) {
            return [];
        }

        $sensorIds = array_column($sensors, 'id');
        $zones = $this->zones(array_unique(array_column($sensors, 'zone_id')));
        $certificates = SensorCalibrationCertificateModel::whereIn('sensor_id', $sensorIds)
            ->where('is_current', true)
            ->get()
            ->keyBy('sensor_id');
        $lastReadings = $this->lastReadings($sensorIds);

        $overview = [];

        foreach ($sensors as $sensor) {
            $overview[$sensor['id']] = [
                'zone'         => $zones[$sensor['zone_id']] ?? null,
                'calibration'  => $this->calibration($certificates->get($sensor['id'])),
                'last_reading' => $lastReadings[$sensor['id']] ?? null,
            ];
        }

        return $overview;
    }

    /** Último registro de un sensor (por fecha de la lectura). */
    public function lastReading(int $sensorId): ?array
    {
        return $this->lastReadings([$sensorId])[$sensorId] ?? null;
    }

    /** @return array{status: string, status_label: string, id: ?int, expires_at: ?string, days_left: ?int} */
    public function calibration(?SensorCalibrationCertificateModel $certificate): array
    {
        $today = now();
        $status = $this->calibrationStatus->status($certificate?->expires_at, $today);

        return [
            'status'       => $status,
            'status_label' => $this->calibrationStatus->label($status),
            'id'           => $certificate?->id,
            'expires_at'   => $certificate?->expires_at?->toDateString(),
            'days_left'    => $certificate ? $this->calibrationStatus->daysLeft($certificate->expires_at, $today) : null,
        ];
    }

    /** @return array<int, array> */
    private function zones(array $zoneIds): array
    {
        return ZoneModel::withTrashed()
            ->with(['warehouse' => fn ($q) => $q->withTrashed()])
            ->whereIn('id', $zoneIds)
            ->get()
            ->mapWithKeys(fn (ZoneModel $z) => [$z->id => [
                'id'             => $z->id,
                'name'           => $z->name,
                'code'           => $z->code,
                'type'           => $z->type,
                'temp_min'       => $z->temp_min !== null ? (float) $z->temp_min : null,
                'temp_max'       => $z->temp_max !== null ? (float) $z->temp_max : null,
                'humidity_min'   => $z->humidity_min !== null ? (float) $z->humidity_min : null,
                'humidity_max'   => $z->humidity_max !== null ? (float) $z->humidity_max : null,
                'warehouse_id'   => $z->warehouse_id,
                'warehouse_name' => $z->warehouse?->name,
            ]])
            ->all();
    }

    /** @return array<int, array{id: int, value: float, recorded_at: string, created_at: ?string, reading_source: string, user_name: ?string}> */
    private function lastReadings(array $sensorIds): array
    {
        $latest = DB::table('sensor_readings')
            ->selectRaw('sensor_id, MAX(recorded_at) AS max_recorded_at')
            ->whereIn('sensor_id', $sensorIds)
            ->groupBy('sensor_id');

        return SensorReadingModel::query()
            ->select('sensor_readings.*')
            ->joinSub($latest, 'latest', function ($join) {
                $join->on('latest.sensor_id', '=', 'sensor_readings.sensor_id')
                    ->on('latest.max_recorded_at', '=', 'sensor_readings.recorded_at');
            })
            ->with('user:id,name')
            ->orderBy('sensor_readings.id')
            ->get()
            ->keyBy('sensor_id')
            ->map(fn (SensorReadingModel $r) => [
                'id'             => $r->id,
                'value'          => (float) $r->value,
                'recorded_at'    => $r->recorded_at->format('Y-m-d H:i:s'),
                'created_at'     => $r->created_at?->format('Y-m-d H:i:s'),
                'reading_source' => $r->reading_source,
                'user_name'      => $r->user?->name,
            ])
            ->all();
    }
}
