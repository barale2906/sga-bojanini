<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\ProductVariant;

interface ProductVariantRepositoryInterface
{
    public function findById(int $id): ?ProductVariant;

    /** @return ProductVariant[] */
    public function findByGenericProduct(int $genericProductId): array;

    public function save(ProductVariant $variant): ProductVariant;

    public function delete(int $id): void;
}
