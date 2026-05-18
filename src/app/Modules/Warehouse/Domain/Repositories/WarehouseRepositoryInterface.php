<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Repositories;

use App\Modules\Warehouse\Domain\Entities\Warehouse;

interface WarehouseRepositoryInterface
{
    public function findById(int $id): ?Warehouse;

    public function findByCode(string $code): ?Warehouse;

    public function findAll(array $filters = []): array;

    public function save(Warehouse $warehouse): Warehouse;

    public function delete(int $id): void;
}
