<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Application\DTOs\ZoneData;
use App\Modules\Warehouse\Domain\Entities\Zone;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class UpdateZoneUseCase
{
    public function __construct(
        private readonly ZoneRepositoryInterface $repository,
    ) {}

    public function execute(int $id, ZoneData $data): Zone
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Zona no encontrada.');
        }

        $duplicate = $this->repository->findByWarehouseAndCode($data->warehouseId, $data->code);

        if ($duplicate !== null && $duplicate->getId() !== $id) {
            throw new \DomainException("Ya existe una zona con el código '{$data->code}' en este almacén.");
        }

        $zone = new Zone(
            id: $id,
            warehouseId: $data->warehouseId,
            name: $data->name,
            code: $data->code,
            type: $data->type,
            tempMin: $data->tempMin,
            tempMax: $data->tempMax,
            humidityMin: $data->humidityMin,
            humidityMax: $data->humidityMax,
            description: $data->description,
            isActive: $existing->isActive(),
        );

        return $this->repository->save($zone);
    }
}
