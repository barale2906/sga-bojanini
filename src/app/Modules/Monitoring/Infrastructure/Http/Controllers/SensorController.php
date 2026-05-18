<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Controllers;

use App\Modules\Monitoring\Application\DTOs\SensorData;
use App\Modules\Monitoring\Application\UseCases\CreateSensorUseCase;
use App\Modules\Monitoring\Application\UseCases\DeleteSensorUseCase;
use App\Modules\Monitoring\Application\UseCases\UpdateSensorUseCase;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Http\Requests\StoreSensorRequest;
use App\Modules\Monitoring\Infrastructure\Http\Requests\UpdateSensorRequest;
use App\Modules\Monitoring\Infrastructure\Http\Resources\SensorResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SensorController extends Controller
{
    use ApiResponse;

    public function index(Request $request, SensorRepositoryInterface $repository): JsonResponse
    {
        $sensors = $repository->findAll([
            'zone_id'   => $request->query('zone_id'),
            'type'      => $request->query('type'),
            'is_active' => $request->query('is_active'),
        ]);

        return $this->success(SensorResource::collection($sensors), 'Listado de sensores');
    }

    public function store(StoreSensorRequest $request, CreateSensorUseCase $useCase): JsonResponse
    {
        $data = new SensorData(
            zoneId: $request->validated('zone_id'),
            code: $request->validated('code'),
            name: $request->validated('name'),
            type: $request->validated('type'),
            unit: $request->validated('unit'),
        );

        $sensor = $useCase->execute($data);

        return $this->created(new SensorResource($sensor), 'Sensor creado exitosamente');
    }

    public function show(int $sensor, SensorRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($sensor);

        if ($entity === null) {
            return $this->error('Sensor no encontrado', 404);
        }

        return $this->success(new SensorResource($entity), 'Detalle del sensor');
    }

    public function update(int $sensor, UpdateSensorRequest $request, UpdateSensorUseCase $useCase): JsonResponse
    {
        $data = new SensorData(
            zoneId: $request->validated('zone_id'),
            code: $request->validated('code'),
            name: $request->validated('name'),
            type: $request->validated('type'),
            unit: $request->validated('unit'),
        );

        $entity = $useCase->execute($sensor, $data);

        return $this->success(new SensorResource($entity), 'Sensor actualizado exitosamente');
    }

    public function destroy(int $sensor, DeleteSensorUseCase $useCase): JsonResponse
    {
        $useCase->execute($sensor);

        return $this->noContent('Sensor desactivado');
    }
}
