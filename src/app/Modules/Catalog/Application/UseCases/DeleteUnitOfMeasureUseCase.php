<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;

class DeleteUnitOfMeasureUseCase
{
    public function __construct(
        private readonly UnitOfMeasureRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Unidad de medida no encontrada.');
        }

        $this->repository->delete($id);
    }
}
