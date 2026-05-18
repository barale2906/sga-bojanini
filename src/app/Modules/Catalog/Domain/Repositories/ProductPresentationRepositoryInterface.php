<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;

interface ProductPresentationRepositoryInterface
{
    public function findById(int $id): ?ProductPresentation;

    /** @return ProductPresentation[] */
    public function findByProductId(int $productId): array;

    public function save(ProductPresentation $presentation): ProductPresentation;

    public function delete(int $id): void;
}
