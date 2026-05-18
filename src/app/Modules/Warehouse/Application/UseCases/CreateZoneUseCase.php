<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Application\DTOs\ZoneData;
use App\Modules\Warehouse\Domain\Entities\Zone;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class CreateZoneUseCase
{
    public function __construct(
        private readonly ZoneRepositoryInterface $zoneRepository,
        private readonly WarehouseRepositoryInterface $warehouseRepository,
    ) {}

    public function execute(ZoneData $data): Zone
    {
        if ($this->warehouseRepository->findById($data->warehouseId) === null) {
            throw new \DomainException('El almacén indicado no existe.');
        }

        if ($this->zoneRepository->findByWarehouseAndCode($data->warehouseId, $data->code) !== null) {
            throw new \DomainException("Ya existe una zona con el código '{$data->code}' en este almacén.");
        }

        $zone = new Zone(
            id: null,
            warehouseId: $data->warehouseId,
            name: $data->name,
            code: $data->code,
            type: $data->type,
            tempMin: $data->tempMin,
            tempMax: $data->tempMax,
            humidityMin: $data->humidityMin,
            humidityMax: $data->humidityMax,
            description: $data->description,
        );

        return $this->zoneRepository->save($zone);
    }
}
