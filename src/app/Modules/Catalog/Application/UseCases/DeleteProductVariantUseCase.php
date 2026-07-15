<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductVariantRepositoryInterface;

class DeleteProductVariantUseCase
{
    public function __construct(
        private readonly ProductVariantRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Variante de producto no encontrada.');
        }

        $this->repository->delete($id);
    }
}
