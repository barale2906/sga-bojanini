<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;

interface ProductPresentationRepositoryInterface
{
    public function findById(int $id): ?ProductPresentation;

    /** @return ProductPresentation[] */
    public function findAll(): array;

    /** @return ProductPresentation[] */
    public function findByProductId(int $productId): array;

    public function save(ProductPresentation $presentation): ProductPresentation;

    public function delete(int $id): void;

    public function attachToProduct(
        int $presentationId,
        int $productId,
        bool $isPurchaseDefault = false,
        int $sortOrder = 0,
    ): void;

    public function detachFromProduct(int $presentationId, int $productId): void;

    public function setProductDefault(int $presentationId, int $productId): void;
}
