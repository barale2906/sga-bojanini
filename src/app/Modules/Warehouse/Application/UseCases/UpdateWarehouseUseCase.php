<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Application\DTOs\WarehouseData;
use App\Modules\Warehouse\Domain\Entities\Warehouse;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;

class UpdateWarehouseUseCase
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $repository,
    ) {}

    public function execute(int $id, WarehouseData $data): Warehouse
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Almacén no encontrado.');
        }

        $duplicate = $this->repository->findByCode($data->code);

        if ($duplicate !== null && $duplicate->getId() !== $id) {
            throw new \DomainException("Ya existe un almacén con el código '{$data->code}'.");
        }

        $warehouse = new Warehouse(
            id: $id,
            name: $data->name,
            code: $data->code,
            address: $data->address,
            description: $data->description,
            isActive: $existing->isActive(),
        );

        return $this->repository->save($warehouse);
    }
}
