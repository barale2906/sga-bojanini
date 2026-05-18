<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Controllers;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Warehouse\Application\DTOs\LocationData;
use App\Modules\Warehouse\Application\UseCases\CreateLocationUseCase;
use App\Modules\Warehouse\Application\UseCases\DeleteLocationUseCase;
use App\Modules\Warehouse\Application\UseCases\UpdateLocationUseCase;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Http\Requests\StoreLocationRequest;
use App\Modules\Warehouse\Infrastructure\Http\Requests\UpdateLocationRequest;
use App\Modules\Warehouse\Infrastructure\Http\Resources\LocationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocationController extends Controller
{
    use ApiResponse;

    public function index(Request $request, LocationRepositoryInterface $repository): JsonResponse
    {
        $locations = $repository->findAll([
            'zone_id'   => $request->query('zone_id'),
            'is_active' => $request->query('is_active'),
            'search'    => $request->query('search'),
        ]);

        return $this->success(
            LocationResource::collection($locations),
            'Listado de ubicaciones'
        );
    }

    public function store(StoreLocationRequest $request, CreateLocationUseCase $useCase): JsonResponse
    {
        $data = new LocationData(
            zoneId: $request->validated('zone_id'),
            name: $request->validated('name'),
            code: $request->validated('code'),
            capacity: $request->validated('capacity'),
            description: $request->validated('description'),
        );

        $location = $useCase->execute($data);

        return $this->created(new LocationResource($location), 'Ubicación creada exitosamente');
    }

    public function show(int $location, LocationRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($location);

        if ($entity === null) {
            return $this->error('Ubicación no encontrada', 404);
        }

        return $this->success(new LocationResource($entity), 'Detalle de la ubicación');
    }

    public function update(int $location, UpdateLocationRequest $request, UpdateLocationUseCase $useCase): JsonResponse
    {
        $data = new LocationData(
            zoneId: $request->validated('zone_id'),
            name: $request->validated('name'),
            code: $request->validated('code'),
            capacity: $request->validated('capacity'),
            description: $request->validated('description'),
        );

        $entity = $useCase->execute($location, $data);

        return $this->success(new LocationResource($entity), 'Ubicación actualizada exitosamente');
    }

    public function destroy(int $location, DeleteLocationUseCase $useCase): JsonResponse
    {
        $useCase->execute($location);

        return $this->noContent('Ubicación eliminada');
    }
}
