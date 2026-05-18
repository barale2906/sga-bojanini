<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Repositories;

use App\Modules\Warehouse\Domain\Entities\Location;

interface LocationRepositoryInterface
{
    public function findById(int $id): ?Location;

    public function findAll(array $filters = []): array;

    public function findByZoneId(int $zoneId): array;

    public function findByWarehouseId(int $warehouseId): array;

    public function findByZoneAndCode(int $zoneId, string $code): ?Location;

    public function save(Location $location): Location;

    public function delete(int $id): void;
}
