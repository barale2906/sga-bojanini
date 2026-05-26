<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;

class AttachPresentationToProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
    ) {}

    public function execute(int $productId, int $presentationId, array $data = []): void
    {
        $product = $this->productRepository->findById($productId);

        if ($product === null) {
            throw new \DomainException('Producto no encontrado.');
        }

        if ($product->isKit()) {
            throw new \DomainException('Los kits no admiten presentaciones de empaque.');
        }

        if ($this->presentationRepository->findById($presentationId) === null) {
            throw new \DomainException('Presentación no encontrada.');
        }

        $this->presentationRepository->attachToProduct(
            presentationId: $presentationId,
            productId: $productId,
            isPurchaseDefault: (bool) ($data['is_purchase_default'] ?? false),
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }
}
