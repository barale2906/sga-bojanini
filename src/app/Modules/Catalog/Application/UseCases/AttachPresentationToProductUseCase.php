<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;

class AttachPresentationToProductUseCase
{
    public function __construct(
        private readonly GenericProductRepositoryInterface $genericProductRepository,
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
    ) {}

    public function execute(int $genericProductId, int $presentationId, array $data = []): void
    {
        $product = $this->genericProductRepository->findById($genericProductId);

        if ($product === null) {
            throw new \DomainException('Producto genérico no encontrado.');
        }

        if ($product->isKit()) {
            throw new \DomainException('Los kits no admiten presentaciones de empaque.');
        }

        if ($this->presentationRepository->findById($presentationId) === null) {
            throw new \DomainException('Presentación no encontrada.');
        }

        $this->presentationRepository->attachToProduct(
            presentationId: $presentationId,
            productId: $genericProductId,
            isPurchaseDefault: (bool) ($data['is_purchase_default'] ?? false),
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }
}
