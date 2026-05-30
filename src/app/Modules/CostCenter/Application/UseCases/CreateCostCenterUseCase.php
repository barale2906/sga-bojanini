<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\CostCenterData;
use App\Modules\CostCenter\Domain\Entities\CostCenter;
use App\Modules\CostCenter\Domain\Enums\CostCenterType;
use App\Modules\CostCenter\Domain\Repositories\CostCenterRepositoryInterface;

class CreateCostCenterUseCase
{
    public function __construct(
        private readonly CostCenterRepositoryInterface $repository,
    ) {}

    public function execute(CostCenterData $data): CostCenter
    {
        if ($this->repository->findByCode($data->code) !== null) {
            throw new \DomainException("Ya existe un centro de costo con el código '{$data->code}'.");
        }

        $costCenter = new CostCenter(
            id:          null,
            code:        strtoupper(trim($data->code)),
            name:        $data->name,
            type:        CostCenterType::from($data->type),
            description: $data->description,
            isActive:    $data->isActive,
        );

        return $this->repository->save($costCenter);
    }
}
