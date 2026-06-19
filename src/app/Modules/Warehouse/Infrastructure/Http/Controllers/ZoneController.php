<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Controllers;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use App\Modules\Warehouse\Application\DTOs\ZoneData;
use App\Modules\Warehouse\Application\UseCases\CreateZoneUseCase;
use App\Modules\Warehouse\Application\UseCases\DeleteZoneUseCase;
use App\Modules\Warehouse\Application\UseCases\UpdateZoneUseCase;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Http\Requests\StoreZoneRequest;
use App\Modules\Warehouse\Infrastructure\Http\Requests\UpdateZoneRequest;
use App\Modules\Warehouse\Infrastructure\Http\Resources\ZoneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ZoneController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function index(Request $request, ZoneRepositoryInterface $repository): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');

        if ($warehouseId !== null) {
            $this->assertWarehouseAccess($request->user(), (int) $warehouseId);
        }

        $zones = $repository->findAll([
            'warehouse_id'  => $warehouseId,
            'warehouse_ids' => $this->allowedWarehouseIds($request->user()),
            'is_active'     => $request->query('is_active'),
            'type'          => $request->query('type'),
            'search'        => $request->query('search'),
        ]);

        return $this->success(
            ZoneResource::collection($zones),
            'Listado de zonas'
        );
    }

    public function store(StoreZoneRequest $request, CreateZoneUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

        $data = new ZoneData(
            warehouseId: $request->validated('warehouse_id'),
            name: $request->validated('name'),
            code: $request->validated('code'),
            type: $request->validated('type'),
            tempMin: $request->validated('temp_min'),
            tempMax: $request->validated('temp_max'),
            humidityMin: $request->validated('humidity_min'),
            humidityMax: $request->validated('humidity_max'),
            description: $request->validated('description'),
        );

        $zone = $useCase->execute($data);

        return $this->created(new ZoneResource($zone), 'Zona creada exitosamente');
    }

    public function show(int $zone, Request $request, ZoneRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($zone);

        if ($entity === null) {
            return $this->error('Zona no encontrada', 404);
        }

        $this->assertWarehouseAccess($request->user(), $entity->getWarehouseId());

        return $this->success(new ZoneResource($entity), 'Detalle de la zona');
    }

    public function update(int $zone, UpdateZoneRequest $request, UpdateZoneUseCase $useCase, ZoneRepositoryInterface $repository): JsonResponse
    {
        $existing = $repository->findById($zone);

        if ($existing === null) {
            return $this->error('Zona no encontrada', 404);
        }

        $this->assertWarehouseAccess($request->user(), $existing->getWarehouseId());
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

        $data = new ZoneData(
            warehouseId: $request->validated('warehouse_id'),
            name: $request->validated('name'),
            code: $request->validated('code'),
            type: $request->validated('type'),
            tempMin: $request->validated('temp_min'),
            tempMax: $request->validated('temp_max'),
            humidityMin: $request->validated('humidity_min'),
            humidityMax: $request->validated('humidity_max'),
            description: $request->validated('description'),
        );

        $entity = $useCase->execute($zone, $data);

        return $this->success(new ZoneResource($entity), 'Zona actualizada exitosamente');
    }

    public function destroy(int $zone, Request $request, DeleteZoneUseCase $useCase, ZoneRepositoryInterface $repository): JsonResponse
    {
        $existing = $repository->findById($zone);

        if ($existing === null) {
            return $this->error('Zona no encontrada', 404);
        }

        $this->assertWarehouseAccess($request->user(), $existing->getWarehouseId());

        $useCase->execute($zone);

        return $this->noContent('Zona eliminada');
    }
}
