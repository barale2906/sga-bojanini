<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Controllers;

use App\Modules\Auth\Infrastructure\Http\Resources\UserResource;
use App\Modules\Monitoring\Application\DTOs\SensorData;
use App\Modules\Monitoring\Application\UseCases\CreateSensorUseCase;
use App\Modules\Monitoring\Application\UseCases\DeleteSensorUseCase;
use App\Modules\Monitoring\Application\UseCases\UpdateSensorUseCase;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Http\Requests\StoreSensorRequest;
use App\Modules\Monitoring\Infrastructure\Http\Requests\UpdateSensorRequest;
use App\Modules\Monitoring\Infrastructure\Http\Resources\SensorResource;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksSensorAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SensorController extends Controller
{
    use ApiResponse;
    use ChecksSensorAccess;

    public function index(Request $request, SensorRepositoryInterface $repository): JsonResponse
    {
        $sensors = $repository->findAll([
            'zone_id' => $request->query('zone_id'),
            'type' => $request->query('type'),
            'is_active' => $request->query('is_active'),
            'ids' => $this->allowedSensorIds($request->user()),
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

    public function show(int $sensor, Request $request, SensorRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($sensor);

        if ($entity === null) {
            return $this->error('Sensor no encontrado', 404);
        }

        $this->assertSensorAccess($request->user(), $sensor);

        return $this->success(new SensorResource($entity), 'Detalle del sensor');
    }

    public function update(int $sensor, UpdateSensorRequest $request, UpdateSensorUseCase $useCase): JsonResponse
    {
        $this->assertSensorAccess($request->user(), $sensor);

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
}
