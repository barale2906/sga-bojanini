<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\CostCenterRepositoryInterface;

class ListCostCentersUseCase
{
    public function __construct(
        private readonly CostCenterRepositoryInterface $repository,
    ) {}

    public function execute(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }
}
