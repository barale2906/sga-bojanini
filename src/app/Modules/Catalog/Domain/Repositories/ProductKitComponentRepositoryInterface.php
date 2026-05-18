<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\ProductKitComponent;

interface ProductKitComponentRepositoryInterface
{
    /** @return ProductKitComponent[] */
    public function findByKitProductId(int $kitProductId, bool $activeOnly = true): array;

    public function save(ProductKitComponent $component): ProductKitComponent;

    public function deleteByKitProductId(int $kitProductId): void;
}
