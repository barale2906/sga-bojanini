<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\ProcedurePriceRepositoryInterface;

class DeleteProcedurePriceUseCase
{
    public function __construct(
        private readonly ProcedurePriceRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Tarifa con id {$id} no encontrada.");
        }

        $this->repository->delete($id);
    }
}
