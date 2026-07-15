<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;

class DeleteGenericProductUseCase
{
    public function __construct(
        private readonly GenericProductRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Producto genérico no encontrado.');
        }

        $this->repository->delete($id);
    }
}
