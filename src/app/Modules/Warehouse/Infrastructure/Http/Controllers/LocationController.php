<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Controllers;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use App\Modules\Warehouse\Application\DTOs\LocationData;
use App\Modules\Warehouse\Application\UseCases\CreateLocationUseCase;
use App\Modules\Warehouse\Application\UseCases\DeleteLocationUseCase;
use App\Modules\Warehouse\Application\UseCases\UpdateLocationUseCase;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Http\Requests\StoreLocationRequest;
use App\Modules\Warehouse\Infrastructure\Http\Requests\UpdateLocationRequest;
use App\Modules\Warehouse\Infrastructure\Http\Resources\LocationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocationController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function index(Request $request, LocationRepositoryInterface $repository, ZoneRepositoryInterface $zoneRepository): JsonResponse
    {
        $zoneId = $request->query('zone_id');

        if ($zoneId !== null) {
            $zone = $zoneRepository->findById((int) $zoneId);

            if ($zone !== null) {
                $this->assertWarehouseAccess($request->user(), $zone->getWarehouseId());
            }
        }

        $locations = $repository->findAll([
            'zone_id'       => $zoneId,
            'warehouse_ids' => $this->allowedWarehouseIds($request->user()),
            'is_active'     => $request->query('is_active'),
            'search'        => $request->query('search'),
        ]);

        return $this->success(
            LocationResource::collection($locations),
            'Listado de ubicaciones'
        );
    }

    public function store(StoreLocationRequest $request, CreateLocationUseCase $useCase, ZoneRepositoryInterface $zoneRepository): JsonResponse
    {
        $zone = $zoneRepository->findById((int) $request->validated('zone_id'));

        if ($zone !== null) {
            $this->assertWarehouseAccess($request->user(), $zone->getWarehouseId());
        }

        $data = new LocationData(
            zoneId:      $request->validated('zone_id'),
            name:        $request->validated('name'),
            code:        $request->validated('code'),
            volumeCm3:   $request->validated('volume_cm3') !== null
                            ? (float) $request->validated('volume_cm3')
                            : null,
            maxWeightKg: $request->validated('max_weight_kg') !== null
                            ? (float) $request->validated('max_weight_kg')
                            : null,
            description: $request->validated('description'),
        );

        $location = $useCase->execute($data);

        return $this->created(new LocationResource($location), 'Ubicación creada exitosamente');
    }

    public function show(int $location, Request $request, LocationRepositoryInterface $repository, ZoneRepositoryInterface $zoneRepository): JsonResponse
    {
        $entity = $repository->findById($location);

        if ($entity === null) {
            return $this->error('Ubicación no encontrada', 404);
        }

        $this->assertLocationWarehouseAccess($request, $entity->getZoneId(), $zoneRepository);

        return $this->success(new LocationResource($entity), 'Detalle de la ubicación');
    }

    public function update(int $location, UpdateLocationRequest $request, UpdateLocationUseCase $useCase, LocationRepositoryInterface $repository, ZoneRepositoryInterface $zoneRepository): JsonResponse
    {
        $existing = $repository->findById($location);

        if ($existing === null) {
            return $this->error('Ubicación no encontrada', 404);
        }

        $this->assertLocationWarehouseAccess($request, $existing->getZoneId(), $zoneRepository);
        $this->assertLocationWarehouseAccess($request, (int) $request->validated('zone_id'), $zoneRepository);

        $data = new LocationData(
            zoneId:      $request->validated('zone_id'),
            name:        $request->validated('name'),
            code:        $request->validated('code'),
            volumeCm3:   $request->validated('volume_cm3') !== null
                            ? (float) $request->validated('volume_cm3')
                            : null,
            maxWeightKg: $request->validated('max_weight_kg') !== null
                            ? (float) $request->validated('max_weight_kg')
                            : null,
            description: $request->validated('description'),
        );

        $entity = $useCase->execute($location, $data);

        return $this->success(new LocationResource($entity), 'Ubicación actualizada exitosamente');
    }

    public function destroy(int $location, Request $request, DeleteLocationUseCase $useCase, LocationRepositoryInterface $repository, ZoneRepositoryInterface $zoneRepository): JsonResponse
    {
        $existing = $repository->findById($location);

        if ($existing === null) {
            return $this->error('Ubicación no encontrada', 404);
        }

        $this->assertLocationWarehouseAccess($request, $existing->getZoneId(), $zoneRepository);

        $useCase->execute($location);

        return $this->noContent('Ubicación eliminada');
    }

    /** Resuelve la zona indicada a su almacén y valida el acceso del usuario. */
    private function assertLocationWarehouseAccess(Request $request, int $zoneId, ZoneRepositoryInterface $zoneRepository): void
    {
        $zone = $zoneRepository->findById($zoneId);

        if ($zone !== null) {
            $this->assertWarehouseAccess($request->user(), $zone->getWarehouseId());
        }
    }
}
