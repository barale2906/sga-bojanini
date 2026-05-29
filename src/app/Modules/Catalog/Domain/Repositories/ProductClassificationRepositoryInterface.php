<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\ProductClassification;

interface ProductClassificationRepositoryInterface
{
    public function findById(int $id): ?ProductClassification;

    public function findByCode(string $code): ?ProductClassification;

    /** @return ProductClassification[] */
    public function findAll(array $filters = []): array;

    public function save(ProductClassification $classification): ProductClassification;

    public function delete(int $id): void;
}
