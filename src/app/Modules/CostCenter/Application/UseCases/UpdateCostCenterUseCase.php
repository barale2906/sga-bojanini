<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\CostCenterData;
use App\Modules\CostCenter\Domain\Entities\CostCenter;
use App\Modules\CostCenter\Domain\Enums\CostCenterType;
use App\Modules\CostCenter\Domain\Repositories\CostCenterRepositoryInterface;

class UpdateCostCenterUseCase
{
    public function __construct(
        private readonly CostCenterRepositoryInterface $repository,
    ) {}

    public function execute(int $id, CostCenterData $data): CostCenter
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException("Centro de costo con id {$id} no encontrado.");
        }

        $codeConflict = $this->repository->findByCode(strtoupper(trim($data->code)));

        if ($codeConflict !== null && $codeConflict->getId() !== $id) {
            throw new \DomainException("Ya existe un centro de costo con el código '{$data->code}'.");
        }

        $updated = new CostCenter(
            id:          $id,
            code:        strtoupper(trim($data->code)),
            name:        $data->name,
            type:        CostCenterType::from($data->type),
            description: $data->description,
            isActive:    $data->isActive,
        );

        return $this->repository->save($updated);
    }
}
