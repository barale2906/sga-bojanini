<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;

class DeleteLocationUseCase
{
    public function __construct(
        private readonly LocationRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Ubicación no encontrada.');
        }

        $this->repository->delete($id);
    }
}
