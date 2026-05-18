<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Entities\ProductKitComponent;
use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;
use App\Modules\Catalog\Domain\Services\KitRecipeValidator;

class SyncKitComponentsUseCase
{
    public function __construct(
        private readonly ProductKitComponentRepositoryInterface $repository,
        private readonly KitRecipeValidator $validator,
    ) {}

    /**
     * @param array<int, array{component_product_id: int, quantity_per_kit: int, sort_order?: int, notes?: string}> $components
     * @return ProductKitComponent[]
     */
    public function execute(int $kitProductId, array $components): array
    {
        $this->validator->validate($kitProductId, $components);

        $this->repository->deleteByKitProductId($kitProductId);

        $saved = [];

        foreach ($components as $index => $row) {
            $saved[] = $this->repository->save(new ProductKitComponent(
                id: null,
                kitProductId: $kitProductId,
                componentProductId: (int) $row['component_product_id'],
                quantityPerKit: (int) $row['quantity_per_kit'],
                sortOrder: (int) ($row['sort_order'] ?? $index),
                notes: $row['notes'] ?? null,
            ));
        }

        return $saved;
    }
}
