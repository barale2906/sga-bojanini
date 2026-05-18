<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\Supplier;

interface SupplierRepositoryInterface
{
    public function findById(int $id): ?Supplier;

    public function findAll(array $filters = []): array;

    public function save(Supplier $supplier): Supplier;

    public function delete(int $id): void;
}
