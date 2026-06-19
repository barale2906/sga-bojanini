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
use App\Modules\Monitoring\Infrastructure\Http\Resources\ReadingResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksSensorAccess;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller para las lecturas de sensores.
 *
 * Endpoints:
 *   POST /api/v1/sensors/{sensorId}/readings       → store()
 *   POST /api/v1/sensors/readings/bulk              → storeBulk()
 *   GET  /api/v1/sensors/{sensorId}/readings        → index()
 *   GET  /api/v1/sensors/{sensorId}/statistics      → statistics()
 *   GET  /api/v1/sensors/{sensorId}/trend           → trend()
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
}
