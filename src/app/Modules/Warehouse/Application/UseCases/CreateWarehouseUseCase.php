<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Application\DTOs\WarehouseData;
use App\Modules\Warehouse\Domain\Entities\Warehouse;
use App\Modules\Warehouse\Domain\Repositories\UserWarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;

class CreateWarehouseUseCase
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $repository,
        private readonly UserWarehouseRepositoryInterface $userWarehouseRepository,
    ) {}

    public function execute(WarehouseData $data): Warehouse
    {
        if ($this->repository->findByCode($data->code) !== null) {
            throw new \DomainException("Ya existe un almacén con el código '{$data->code}'.");
        }

        $warehouse = new Warehouse(
            id: null,
            name: $data->name,
            code: $data->code,
            address: $data->address,
            description: $data->description,
        );

        $saved = $this->repository->save($warehouse);

        // Todo almacén nuevo queda asignado por defecto al super_administrador.
        $this->userWarehouseRepository->assignWarehouseToUsersWithRole($saved->getId(), 'super_administrador');

        return $saved;
    }
}
