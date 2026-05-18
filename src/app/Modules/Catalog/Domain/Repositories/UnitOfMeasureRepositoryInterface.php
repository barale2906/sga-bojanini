<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\UnitOfMeasure;

interface UnitOfMeasureRepositoryInterface
{
    public function findById(int $id): ?UnitOfMeasure;

    public function findAll(array $filters = []): array;

    public function save(UnitOfMeasure $unitOfMeasure): UnitOfMeasure;

    public function delete(int $id): void;
}
