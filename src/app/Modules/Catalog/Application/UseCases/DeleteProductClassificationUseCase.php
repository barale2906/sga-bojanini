<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;

class DeleteProductClassificationUseCase
{
    public function __construct(
        private readonly ProductClassificationRepositoryInterface $repository,
    ) {}

    /**
     * @throws \DomainException Si la clasificación no existe.
     */
    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Clasificación de producto no encontrada.');
        }

        $this->repository->delete($id);
    }
}
