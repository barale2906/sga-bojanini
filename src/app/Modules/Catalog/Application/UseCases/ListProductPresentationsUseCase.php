<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;

class ListProductPresentationsUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
    ) {}

    public function execute(int $productId): array
    {
        if ($this->productRepository->findById($productId) === null) {
            throw new \DomainException('Producto no encontrado.');
        }

        return $this->presentationRepository->findByProductId($productId);
    }
}
