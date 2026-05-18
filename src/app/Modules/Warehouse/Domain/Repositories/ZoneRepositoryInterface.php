<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Repositories;

use App\Modules\Warehouse\Domain\Entities\Zone;

interface ZoneRepositoryInterface
{
    public function findById(int $id): ?Zone;

    public function findAll(array $filters = []): array;

    public function findByWarehouseId(int $warehouseId): array;

    public function findByWarehouseAndCode(int $warehouseId, string $code): ?Zone;

    public function save(Zone $zone): Zone;

    public function delete(int $id): void;
}
