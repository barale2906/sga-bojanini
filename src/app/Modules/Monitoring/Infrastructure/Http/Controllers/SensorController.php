<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Controllers;

use App\Modules\Auth\Infrastructure\Http\Resources\UserResource;
use App\Modules\Monitoring\Application\DTOs\SensorData;
use App\Modules\Monitoring\Application\UseCases\CreateSensorUseCase;
use App\Modules\Monitoring\Application\UseCases\DeleteSensorUseCase;
use App\Modules\Monitoring\Application\UseCases\RegisterCalibrationCertificateUseCase;
use App\Modules\Monitoring\Application\UseCases\UpdateSensorUseCase;
use App\Modules\Monitoring\Domain\Entities\Sensor;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Http\Requests\StoreSensorRequest;
use App\Modules\Monitoring\Infrastructure\Http\Requests\UpdateSensorRequest;
use App\Modules\Monitoring\Infrastructure\Http\Resources\SensorResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Monitoring\Infrastructure\Queries\SensorOverviewQuery;
use App\Modules\Shared\Application\Services\CodeGeneratorService;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksSensorAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class SensorController extends Controller
{
    use ApiResponse;
    use ChecksSensorAccess;

    public function index(Request $request, SensorRepositoryInterface $repository, SensorOverviewQuery $overviewQuery): JsonResponse
    {
        $sensors = $repository->findAll([
            'zone_id' => $request->query('zone_id'),
            'type' => $request->query('type'),
            'is_active' => $request->query('is_active'),
            'ids' => $this->allowedSensorIds($request->user()),
        ]);

        $overview = $overviewQuery->forSensors(array_map(
            fn ($s) => ['id' => $s->getId(), 'zone_id' => $s->getZoneId()],
            $sensors,
        ));

        $data = array_map(
            fn ($s) => (new SensorResource($s))->withOverview($overview[$s->getId()] ?? null),
            $sensors,
        );

        return $this->success($data, 'Listado de sensores');
    }

    /**
     * Crea el sensor. Si llega `certificate_file` (multipart) se registra
     * en la misma transacción como su certificado de calibración vigente.
     */
    public function store(
        StoreSensorRequest $request,
        CreateSensorUseCase $useCase,
        RegisterCalibrationCertificateUseCase $registerCertificate,
        SensorOverviewQuery $overviewQuery,
    ): JsonResponse {
        $name = $request->validated('name');
        $code = $request->validated('code') ?: CodeGeneratorService::fromName($name);

        $data = new SensorData(
            zoneId: $request->validated('zone_id'),
            code: $code,
            name: $name,
            type: $request->validated('type'),
            unit: $request->validated('unit'),
        );

        $certificate = $request->certificateData();
        $userId = (int) $request->user()->id;

        $sensor = DB::transaction(function () use ($useCase, $data, $certificate, $registerCertificate, $userId) {
            $sensor = $useCase->execute($data);

            if ($certificate !== null) {
                $registerCertificate->execute($sensor->getId(), $certificate, $userId);
            }

            return $sensor;
        });

        return $this->created($this->withOverview($sensor, $overviewQuery), 'Sensor creado exitosamente');
    }

    public function show(int $sensor, Request $request, SensorRepositoryInterface $repository, SensorOverviewQuery $overviewQuery): JsonResponse
    {
        $entity = $repository->findById($sensor);

        if ($entity === null) {
            return $this->error('Sensor no encontrado', 404);
        }

        $this->assertSensorAccess($request->user(), $sensor);

        return $this->success($this->withOverview($entity, $overviewQuery), 'Detalle del sensor');
    }

    public function update(int $sensor, UpdateSensorRequest $request, UpdateSensorUseCase $useCase, SensorOverviewQuery $overviewQuery): JsonResponse
    {
        $this->assertSensorAccess($request->user(), $sensor);

        $name = $request->validated('name');
        $code = $request->validated('code') ?: CodeGeneratorService::fromName($name);

        $data = new SensorData(
            zoneId: $request->validated('zone_id'),
            code: $code,
            name: $name,
            type: $request->validated('type'),
            unit: $request->validated('unit'),
            isActive: $request->has('is_active') ? $request->boolean('is_active') : null,
        );

        $entity = $useCase->execute($sensor, $data);

        return $this->success($this->withOverview($entity, $overviewQuery), 'Sensor actualizado exitosamente');
    }

    public function destroy(int $sensor, Request $request, DeleteSensorUseCase $useCase): JsonResponse
    {
        $this->assertSensorAccess($request->user(), $sensor);

        $useCase->execute($sensor);

        return $this->noContent('Sensor desactivado');
    }

    /** Lista los usuarios con acceso explícito a un sensor. */
    public function users(int $id, Request $request, SensorRepositoryInterface $repository): JsonResponse
    {
        if ($repository->findById($id) === null) {
            return $this->error('Sensor no encontrado', 404);
        }

        $this->assertSensorAccess($request->user(), $id);

        $sensor = SensorModel::with('users.roles')->findOrFail($id);

        return $this->success(UserResource::collection($sensor->users), 'Usuarios con acceso al sensor');
    }

    private function withOverview(Sensor $sensor, SensorOverviewQuery $overviewQuery): SensorResource
    {
        $overview = $overviewQuery->forSensors([['id' => $sensor->getId(), 'zone_id' => $sensor->getZoneId()]]);

        return (new SensorResource($sensor))->withOverview($overview[$sensor->getId()] ?? null);
    }
}
