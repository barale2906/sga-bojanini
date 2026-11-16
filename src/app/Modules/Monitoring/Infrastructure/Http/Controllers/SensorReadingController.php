<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Controllers;

use App\Modules\Monitoring\Application\DTOs\ReadingData;
use App\Modules\Monitoring\Application\UseCases\GetControlChartUseCase;
use App\Modules\Monitoring\Application\UseCases\RegisterBulkReadingsUseCase;
use App\Modules\Monitoring\Application\UseCases\RegisterReadingUseCase;
use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Services\TrendAnalysisService;
use App\Modules\Monitoring\Infrastructure\Http\Requests\StoreBulkReadingsRequest;
use App\Modules\Monitoring\Infrastructure\Http\Requests\StoreReadingRequest;
use App\Modules\Monitoring\Infrastructure\Export\SensorReadingsExport;
use App\Modules\Monitoring\Infrastructure\Http\Requests\ExportReadingsRequest;
use App\Modules\Monitoring\Infrastructure\Http\Resources\ReadingResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorReadingModel;
use App\Modules\Monitoring\Infrastructure\Queries\SensorOverviewQuery;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksSensorAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller para las lecturas de sensores.
 *
 * Endpoints:
 *   POST /api/v1/sensors/{sensorId}/readings       → store()
 *   POST /api/v1/sensors/readings/bulk              → storeBulk()
 *   GET  /api/v1/sensors/{sensorId}/readings        → index()
 *   GET  /api/v1/sensors/{sensorId}/statistics      → statistics()
 *   GET  /api/v1/sensors/{sensorId}/trend           → trend()
 *   GET  /api/v1/sensors/{sensorId}/readings/latest → latest()
 *   GET  /api/v1/sensors/{sensorId}/readings/export → export()
 */
class SensorReadingController extends Controller
{
    use ApiResponse;
    use ChecksSensorAccess;

    /**
     * POST /api/v1/sensors/{sensorId}/readings
     * Registra una lectura manual.
     */
    public function store(
        int $sensorId,
        StoreReadingRequest $request,
        RegisterReadingUseCase $useCase,
    ): JsonResponse {
        $this->assertSensorAccess($request->user(), $sensorId);

        $result = $useCase->execute(new ReadingData(
            sensorId: $sensorId,
            value: (float) $request->validated('value'),
            readingSource: $request->validated('reading_source'),
            recordedAt: $request->validated('recorded_at'),
        ));

        return $this->success([
            'reading' => new ReadingResource($result['reading']),
            'alerts' => $result['alerts'],
        ], 'Lectura registrada correctamente.', 201);
    }

    /**
     * POST /api/v1/sensors/readings/bulk
     * Registra lecturas masivas desde dispositivo IoT.
     */
    public function storeBulk(
        StoreBulkReadingsRequest $request,
        RegisterBulkReadingsUseCase $useCase,
    ): JsonResponse {
        $result = $useCase->execute($request->validated('readings'));

        return $this->success($result, 'Lecturas procesadas correctamente.', 201);
    }

    /**
     * GET /api/v1/sensors/{sensorId}/readings?date_from=...&date_to=...
     * Lista las lecturas de un sensor en un periodo.
     */
    public function index(
        int $sensorId,
        Request $request,
        SensorReadingRepositoryInterface $repository,
    ): JsonResponse {
        $this->assertSensorAccess($request->user(), $sensorId);

        $from = Carbon::parse($request->query('date_from', now()->subDays(7)->toDateString()));
        $to = Carbon::parse($request->query('date_to', now()->toDateString()));

        $readings = $repository->findBySensorAndDateRange($sensorId, $from, $to);

        return $this->success(ReadingResource::collection($readings));
    }

    /**
     * GET /api/v1/sensors/{sensorId}/statistics?date_from=...&date_to=...
     * Retorna el gráfico de control estadístico.
     */
    public function statistics(
        int $sensorId,
        Request $request,
        GetControlChartUseCase $useCase,
    ): JsonResponse {
        $this->assertSensorAccess($request->user(), $sensorId);

        $result = $useCase->execute(
            $sensorId,
            $request->query('date_from', now()->subDays(30)->toDateString()),
            $request->query('date_to', now()->toDateString()),
        );

        return $this->success($result);
    }

    /**
     * GET /api/v1/sensors/{sensorId}/trend?date_from=...&date_to=...
     * Retorna análisis de tendencia del sensor.
     */
    public function trend(
        int $sensorId,
        Request $request,
        TrendAnalysisService $trendService,
    ): JsonResponse {
        $this->assertSensorAccess($request->user(), $sensorId);

        $from = Carbon::parse($request->query('date_from', now()->subDays(7)->toDateString()));
        $to = Carbon::parse($request->query('date_to', now()->toDateString()));

        $result = $trendService->analyzeTrend($sensorId, $from, $to);

        return $this->success($result);
    }

    /**
     * GET /api/v1/sensors/{sensorId}/readings/latest
     * Último registro del equipo (fecha/hora de la lectura y quién la hizo).
     * `data` es null si el equipo aún no tiene lecturas.
     */
    public function latest(int $sensorId, Request $request, SensorOverviewQuery $overviewQuery): JsonResponse
    {
        SensorModel::findOrFail($sensorId);
        $this->assertSensorAccess($request->user(), $sensorId);

        return $this->success($overviewQuery->lastReading($sensorId), 'Último registro del equipo');
    }

    /**
     * GET /api/v1/sensors/{sensorId}/readings/export?date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
     * Descarga en Excel las lecturas del periodo con el resumen estadístico.
     */
    public function export(
        int $sensorId,
        ExportReadingsRequest $request,
        GetControlChartUseCase $controlChart,
        SensorOverviewQuery $overviewQuery,
    ): BinaryFileResponse {
        $sensor = SensorModel::findOrFail($sensorId);
        $this->assertSensorAccess($request->user(), $sensorId);

        $from = Carbon::parse($request->validated('date_from'))->startOfDay();
        $to = Carbon::parse($request->validated('date_to'))->endOfDay();

        $readings = SensorReadingModel::with('user:id,name')
            ->where('sensor_id', $sensorId)
            ->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get();

        $overview = $overviewQuery->forSensors([['id' => $sensor->id, 'zone_id' => $sensor->zone_id]]);

        $export = new SensorReadingsExport(
            sensor: ['name' => $sensor->name, 'code' => $sensor->code, 'type' => $sensor->type, 'unit' => $sensor->unit],
            zone: $overview[$sensor->id]['zone'] ?? null,
            readings: $readings,
            stats: $controlChart->execute($sensorId, $from->toDateString(), $to->toDateString()),
            dateFrom: $from->toDateString(),
            dateTo: $to->toDateString(),
        );

        $filename = 'lecturas_' . Str::slug($sensor->code) . "_{$from->format('Ymd')}_{$to->format('Ymd')}.xlsx";

        return Excel::download($export, $filename);
    }
}
