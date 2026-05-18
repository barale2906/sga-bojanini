<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;

class DeleteWarehouseUseCase
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Almacén no encontrado.');
        }

        $this->repository->delete($id);
    }
}
