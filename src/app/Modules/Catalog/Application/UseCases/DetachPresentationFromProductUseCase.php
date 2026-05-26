<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;

class DetachPresentationFromProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
    ) {}

    public function execute(int $productId, int $presentationId): void
    {
        if ($this->productRepository->findById($productId) === null) {
            throw new \DomainException('Producto no encontrado.');
        }

        if ($this->presentationRepository->findById($presentationId) === null) {
            throw new \DomainException('Presentación no encontrada.');
        }

        $this->presentationRepository->detachFromProduct($presentationId, $productId);
    }
}
