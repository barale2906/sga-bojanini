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

    /**
     * Lista todas las presentaciones existentes (independientes de producto).
     *
     * @return \App\Modules\Catalog\Domain\Entities\ProductPresentation[]
     */
    public function execute(): array
    {
        return $this->presentationRepository->findAll();
    }

    /**
     * Lista las presentaciones asignadas a un producto específico.
     *
     * @return \App\Modules\Catalog\Domain\Entities\ProductPresentation[]
     */
    public function executeForProduct(int $productId): array
    {
        if ($this->productRepository->findById($productId) === null) {
            throw new \DomainException('Producto no encontrado.');
        }

        return $this->presentationRepository->findByProductId($productId);
    }
}
