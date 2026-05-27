<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Controllers;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Warehouse\Application\UseCases\GetLocationCapacityUseCase;
use App\Modules\Warehouse\Application\UseCases\GetWarehouseCapacityUseCase;
use App\Modules\Warehouse\Application\UseCases\GetZoneCapacityUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CapacityController extends Controller
{
    use ApiResponse;

    public function warehouse(int $id, GetWarehouseCapacityUseCase $useCase): JsonResponse
    {
        try {
            $data = $useCase->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success($data, 'Capacidad del almacén');
    }

    public function zone(int $id, GetZoneCapacityUseCase $useCase): JsonResponse
    {
        try {
            $data = $useCase->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success($data, 'Capacidad de la zona');
    }

    public function location(int $id, GetLocationCapacityUseCase $useCase): JsonResponse
    {
        try {
            $data = $useCase->execute($id);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 404);
        }

        return $this->success($data, 'Capacidad de la ubicación');
    }
}
