<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;

class DetachPresentationFromProductUseCase
{
    public function __construct(
        private readonly GenericProductRepositoryInterface $genericProductRepository,
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
    ) {}

    public function execute(int $genericProductId, int $presentationId): void
    {
        if ($this->genericProductRepository->findById($genericProductId) === null) {
            throw new \DomainException('Producto genérico no encontrado.');
        }

        if ($this->presentationRepository->findById($presentationId) === null) {
            throw new \DomainException('Presentación no encontrada.');
        }

        $this->presentationRepository->detachFromProduct($presentationId, $genericProductId);
    }
}
