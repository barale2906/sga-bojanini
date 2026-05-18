<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class DeleteZoneUseCase
{
    public function __construct(
        private readonly ZoneRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Zona no encontrada.');
        }

        $this->repository->delete($id);
    }
}
