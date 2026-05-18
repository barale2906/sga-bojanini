<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\Product;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    public function findByCode(string $code): ?Product;

    public function findAll(array $filters = []): array;

    public function save(Product $product): Product;

    public function delete(int $id): void;
}
