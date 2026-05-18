<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Services\PresentationHierarchyValidator;

class CreateProductPresentationUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
        private readonly PresentationHierarchyValidator $hierarchyValidator,
    ) {}

    public function execute(int $productId, array $data): ProductPresentation
    {
        $product = $this->productRepository->findById($productId);

        if ($product === null) {
            throw new \DomainException('Producto no encontrado.');
        }

        if ($product->isKit()) {
            throw new \DomainException('Los kits no admiten presentaciones de empaque.');
        }

        $data['product_id'] = $productId;
        $this->hierarchyValidator->validate($data);

        return $this->presentationRepository->save(new ProductPresentation(
            id: null,
            productId: $productId,
            parentId: $data['parent_id'] ?? null,
            name: $data['name'],
            code: $data['code'],
            unitsOfMeasureId: (int) $data['units_of_measure_id'],
            quantityPerParent: $data['quantity_per_parent'] ?? null,
            factorToBase: (int) $data['factor_to_base'],
            level: (int) $data['level'],
            isPurchaseDefault: (bool) ($data['is_purchase_default'] ?? false),
            isActive: true,
            sortOrder: (int) ($data['sort_order'] ?? 0),
        ));
    }
}
