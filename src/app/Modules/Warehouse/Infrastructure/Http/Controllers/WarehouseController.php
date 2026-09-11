<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Controllers;

use App\Modules\Auth\Infrastructure\Http\Resources\UserResource;
use App\Modules\Shared\Application\Services\CodeGeneratorService;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use App\Modules\Warehouse\Application\DTOs\UpsertLocationData;
use App\Modules\Warehouse\Application\DTOs\UpsertZoneData;
use App\Modules\Warehouse\Application\DTOs\WarehouseData;
use App\Modules\Warehouse\Application\DTOs\WarehouseStructureData;
use App\Modules\Warehouse\Application\UseCases\CreateWarehouseUseCase;
use App\Modules\Warehouse\Application\UseCases\CreateWarehouseWithStructureUseCase;
use App\Modules\Warehouse\Application\UseCases\DeleteWarehouseUseCase;
use App\Modules\Warehouse\Application\UseCases\UpdateWarehouseUseCase;
use App\Modules\Warehouse\Application\UseCases\UpdateWarehouseWithStructureUseCase;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Http\Requests\StoreWarehouseRequest;
use App\Modules\Warehouse\Infrastructure\Http\Requests\UpdateWarehouseRequest;
use App\Modules\Warehouse\Infrastructure\Http\Resources\LocationResource;
use App\Modules\Warehouse\Infrastructure\Http\Resources\WarehouseResource;
use App\Modules\Warehouse\Infrastructure\Http\Resources\WarehouseWithZonesResource;
use App\Modules\Warehouse\Infrastructure\Http\Resources\ZoneResource;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WarehouseController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function index(Request $request, WarehouseRepositoryInterface $repository): JsonResponse
    {
        $warehouses = $repository->findAll([
            'search'    => $request->query('search'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'ids'       => $this->allowedWarehouseIds($request->user()),
        ]);

        return $this->success(
            WarehouseResource::collection($warehouses),
            'Listado de almacenes'
        );
    }

    public function store(StoreWarehouseRequest $request, CreateWarehouseUseCase $simpleUseCase, CreateWarehouseWithStructureUseCase $structureUseCase): JsonResponse
    {
        $zonesInput = $request->validated('zones');

        $name = $request->validated('name');
        $code = $request->validated('code') ?: CodeGeneratorService::fromName($name);

        if (empty($zonesInput)) {
            $data = new WarehouseData(
                name: $name,
                code: $code,
                address: $request->validated('address'),
                description: $request->validated('description'),
            );
            $warehouse = $simpleUseCase->execute($data);
            return $this->created(new WarehouseResource($warehouse), 'Almacén creado exitosamente');
        }

        $data = new WarehouseStructureData(
            name: $name,
            code: $code,
            address: $request->validated('address'),
            description: $request->validated('description'),
            zones: $this->buildZones($zonesInput),
        );

        $warehouse = $structureUseCase->execute($data);

        $model = WarehouseModel::with('zones.locations')->findOrFail($warehouse->getId());

        return $this->created(new WarehouseWithZonesResource($model), 'Almacén creado exitosamente');
    }

    public function show(int $warehouse, Request $request, WarehouseRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($warehouse);

        if ($entity === null) {
            return $this->error('Almacén no encontrado', 404);
        }

        $this->assertWarehouseAccess($request->user(), $warehouse);

        return $this->success(
            new WarehouseResource($entity),
            'Detalle del almacén'
        );
    }

    public function update(int $warehouse, UpdateWarehouseRequest $request, UpdateWarehouseUseCase $simpleUseCase, UpdateWarehouseWithStructureUseCase $structureUseCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), $warehouse);

        $zonesInput = $request->validated('zones');
        $name = $request->validated('name');
        $code = $request->validated('code') ?: CodeGeneratorService::fromName($name);

        if (empty($zonesInput)) {
            $data = new WarehouseData(
                name: $name,
                code: $code,
                address: $request->validated('address'),
                description: $request->validated('description'),
            );
            $entity = $simpleUseCase->execute($warehouse, $data);
            return $this->success(new WarehouseResource($entity), 'Almacén actualizado exitosamente');
        }

        $data = new WarehouseStructureData(
            name: $name,
            code: $code,
            address: $request->validated('address'),
            description: $request->validated('description'),
            zones: $this->buildZones($zonesInput),
        );

        $structureUseCase->execute($warehouse, $data);

        $model = WarehouseModel::with('zones.locations')->findOrFail($warehouse);

        return $this->success(new WarehouseWithZonesResource($model), 'Almacén actualizado exitosamente');
    }

    public function destroy(int $warehouse, Request $request, DeleteWarehouseUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), $warehouse);

        $useCase->execute($warehouse);

        return $this->noContent('Almacén eliminado');
    }

    public function zones(int $id, Request $request, ZoneRepositoryInterface $repository, WarehouseRepositoryInterface $warehouseRepository): JsonResponse
    {
        if ($warehouseRepository->findById($id) === null) {
            return $this->error('Almacén no encontrado', 404);
        }

        $this->assertWarehouseAccess($request->user(), $id);

        $zones = $repository->findByWarehouseId($id);

        return $this->success(
            ZoneResource::collection($zones),
            'Zonas del almacén'
        );
    }

    public function locations(int $id, Request $request, LocationRepositoryInterface $repository, WarehouseRepositoryInterface $warehouseRepository): JsonResponse
    {
        if ($warehouseRepository->findById($id) === null) {
            return $this->error('Almacén no encontrado', 404);
        }

        $this->assertWarehouseAccess($request->user(), $id);

        $locations = $repository->findByWarehouseId($id);

        return $this->success(
            LocationResource::collection($locations),
            'Ubicaciones del almacén'
        );
    }

    /** @return UpsertZoneData[] */
    private function buildZones(array $zones): array
    {
        return array_map(function (array $zone): UpsertZoneData {
            $zoneName = $zone['name'];
            $zoneCode = ($zone['code'] ?? '') ?: CodeGeneratorService::fromName($zoneName);

            $locations = array_map(
                function (array $loc): UpsertLocationData {
                    $locName = $loc['name'];
                    $locCode = ($loc['code'] ?? '') ?: CodeGeneratorService::fromName($locName);

                    return new UpsertLocationData(
                        id: isset($loc['id']) ? (int) $loc['id'] : null,
                        name: $locName,
                        code: $locCode,
                        volumeCm3: isset($loc['volume_cm3']) ? (float) $loc['volume_cm3'] : null,
                        maxWeightKg: isset($loc['max_weight_kg']) ? (float) $loc['max_weight_kg'] : null,
                        description: $loc['description'] ?? null,
                    );
                },
                $zone['locations'] ?? []
            );

            return new UpsertZoneData(
                id: isset($zone['id']) ? (int) $zone['id'] : null,
                name: $zoneName,
                code: $zoneCode,
                type: $zone['type'],
                tempMin: isset($zone['temp_min']) ? (float) $zone['temp_min'] : null,
                tempMax: isset($zone['temp_max']) ? (float) $zone['temp_max'] : null,
                humidityMin: isset($zone['humidity_min']) ? (float) $zone['humidity_min'] : null,
                humidityMax: isset($zone['humidity_max']) ? (float) $zone['humidity_max'] : null,
                description: $zone['description'] ?? null,
                locations: $locations,
            );
        }, $zones);
    }

    /** Lista los usuarios con acceso explícito a un almacén. */
    public function users(int $id, Request $request, WarehouseRepositoryInterface $repository): JsonResponse
    {
        if ($repository->findById($id) === null) {
            return $this->error('Almacén no encontrado', 404);
        }

        $this->assertWarehouseAccess($request->user(), $id);

        $warehouse = WarehouseModel::with('users.roles')->findOrFail($id);

        return $this->success(UserResource::collection($warehouse->users), 'Usuarios con acceso al almacén');
    }
}
