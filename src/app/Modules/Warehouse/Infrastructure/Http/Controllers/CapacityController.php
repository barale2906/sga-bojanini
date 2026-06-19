<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Controllers;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use App\Modules\Warehouse\Application\UseCases\GetLocationCapacityUseCase;
use App\Modules\Warehouse\Application\UseCases\GetWarehouseCapacityUseCase;
use App\Modules\Warehouse\Application\UseCases\GetZoneCapacityUseCase;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CapacityController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function warehouse(int $id, Request $request, GetWarehouseCapacityUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), $id);

        try {
            $data = $useCase->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success($data, 'Capacidad del almacén');
    }

    public function zone(int $id, Request $request, GetZoneCapacityUseCase $useCase, ZoneRepositoryInterface $zoneRepository): JsonResponse
    {
        $zone = $zoneRepository->findById($id);

        if ($zone !== null) {
            $this->assertWarehouseAccess($request->user(), $zone->getWarehouseId());
        }

        try {
            $data = $useCase->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success($data, 'Capacidad de la zona');
    }

    public function location(
        int $id,
        Request $request,
        GetLocationCapacityUseCase $useCase,
        LocationRepositoryInterface $locationRepository,
        ZoneRepositoryInterface $zoneRepository,
    ): JsonResponse {
        $location = $locationRepository->findById($id);
        $zone = $location !== null ? $zoneRepository->findById($location->getZoneId()) : null;

        if ($zone !== null) {
            $this->assertWarehouseAccess($request->user(), $zone->getWarehouseId());
        }

        try {
            $data = $useCase->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success($data, 'Capacidad de la ubicación');
    }
}
