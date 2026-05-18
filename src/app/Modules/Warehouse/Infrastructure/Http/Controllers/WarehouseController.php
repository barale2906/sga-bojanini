<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Controllers;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Warehouse\Application\DTOs\WarehouseData;
use App\Modules\Warehouse\Application\UseCases\CreateWarehouseUseCase;
use App\Modules\Warehouse\Application\UseCases\DeleteWarehouseUseCase;
use App\Modules\Warehouse\Application\UseCases\UpdateWarehouseUseCase;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Http\Requests\StoreWarehouseRequest;
use App\Modules\Warehouse\Infrastructure\Http\Requests\UpdateWarehouseRequest;
use App\Modules\Warehouse\Infrastructure\Http\Resources\LocationResource;
use App\Modules\Warehouse\Infrastructure\Http\Resources\WarehouseResource;
use App\Modules\Warehouse\Infrastructure\Http\Resources\ZoneResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WarehouseController extends Controller
{
    use ApiResponse;

    public function index(Request $request, WarehouseRepositoryInterface $repository): JsonResponse
    {
        $warehouses = $repository->findAll([
            'search'    => $request->query('search'),
            'is_active' => $request->query('is_active'),
        ]);

        return $this->success(
            WarehouseResource::collection($warehouses),
            'Listado de almacenes'
        );
    }

    public function store(StoreWarehouseRequest $request, CreateWarehouseUseCase $useCase): JsonResponse
    {
        $data = new WarehouseData(
            name: $request->validated('name'),
            code: $request->validated('code'),
            address: $request->validated('address'),
            description: $request->validated('description'),
        );

        $warehouse = $useCase->execute($data);

        return $this->created(
            new WarehouseResource($warehouse),
            'Almacén creado exitosamente'
        );
    }

    public function show(int $warehouse, WarehouseRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($warehouse);

        if ($entity === null) {
            return $this->error('Almacén no encontrado', 404);
        }

        return $this->success(
            new WarehouseResource($entity),
            'Detalle del almacén'
        );
    }

    public function update(int $warehouse, UpdateWarehouseRequest $request, UpdateWarehouseUseCase $useCase): JsonResponse
    {
        $data = new WarehouseData(
            name: $request->validated('name'),
            code: $request->validated('code'),
            address: $request->validated('address'),
            description: $request->validated('description'),
        );

        $entity = $useCase->execute($warehouse, $data);

        return $this->success(
            new WarehouseResource($entity),
            'Almacén actualizado exitosamente'
        );
    }

    public function destroy(int $warehouse, DeleteWarehouseUseCase $useCase): JsonResponse
    {
        $useCase->execute($warehouse);

        return $this->noContent('Almacén eliminado');
    }

    public function zones(int $id, ZoneRepositoryInterface $repository, WarehouseRepositoryInterface $warehouseRepository): JsonResponse
    {
        if ($warehouseRepository->findById($id) === null) {
            return $this->error('Almacén no encontrado', 404);
        }

        $zones = $repository->findByWarehouseId($id);

        return $this->success(
            ZoneResource::collection($zones),
            'Zonas del almacén'
        );
    }

    public function locations(int $id, LocationRepositoryInterface $repository, WarehouseRepositoryInterface $warehouseRepository): JsonResponse
    {
        if ($warehouseRepository->findById($id) === null) {
            return $this->error('Almacén no encontrado', 404);
        }

        $locations = $repository->findByWarehouseId($id);

        return $this->success(
            LocationResource::collection($locations),
            'Ubicaciones del almacén'
        );
    }
}
