<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;

class ListProductPresentationsUseCase
{
    public function __construct(
        private readonly GenericProductRepositoryInterface $genericProductRepository,
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
     * Lista las presentaciones asignadas a un genérico específico.
     *
     * @return \App\Modules\Catalog\Domain\Entities\ProductPresentation[]
     */
    public function executeForProduct(int $genericProductId): array
    {
        if ($this->genericProductRepository->findById($genericProductId) === null) {
            throw new \DomainException('Producto genérico no encontrado.');
        }

        return $this->presentationRepository->findByProductId($genericProductId);
    }
}
