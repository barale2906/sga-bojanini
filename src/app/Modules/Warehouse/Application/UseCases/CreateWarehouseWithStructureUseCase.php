<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Application\DTOs\UpsertZoneData;
use App\Modules\Warehouse\Application\DTOs\UpsertLocationData;
use App\Modules\Warehouse\Application\DTOs\WarehouseStructureData;
use App\Modules\Warehouse\Domain\Entities\Location;
use App\Modules\Warehouse\Domain\Entities\Warehouse;
use App\Modules\Warehouse\Domain\Entities\Zone;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\UserWarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class CreateWarehouseWithStructureUseCase
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouseRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly UserWarehouseRepositoryInterface $userWarehouseRepository,
    ) {}

    public function execute(WarehouseStructureData $data): Warehouse
    {
        if ($this->warehouseRepository->findByCode($data->code) !== null) {
            throw new \DomainException("Ya existe un almacén con el código '{$data->code}'.");
        }

        $warehouse = $this->warehouseRepository->save(new Warehouse(
            id: null,
            name: $data->name,
            code: $data->code,
            address: $data->address,
            description: $data->description,
        ));

        $this->userWarehouseRepository->assignWarehouseToUsersWithRole($warehouse->getId(), 'super_administrador');

        foreach ($data->zones as $zoneData) {
            $zone = $this->createZone($warehouse->getId(), $zoneData);
            foreach ($zoneData->locations as $locationData) {
                $this->createLocation($zone->getId(), $locationData);
            }
        }

        return $warehouse;
    }

    private function createZone(int $warehouseId, UpsertZoneData $data): Zone
    {
        if ($this->zoneRepository->findByWarehouseAndCode($warehouseId, $data->code) !== null) {
            throw new \DomainException("Ya existe una zona con el código '{$data->code}' en este almacén.");
        }

        return $this->zoneRepository->save(new Zone(
            id: null,
            warehouseId: $warehouseId,
            name: $data->name,
            code: $data->code,
            type: $data->type,
            tempMin: $data->tempMin,
            tempMax: $data->tempMax,
            humidityMin: $data->humidityMin,
            humidityMax: $data->humidityMax,
            description: $data->description,
        ));
    }

    private function createLocation(int $zoneId, UpsertLocationData $data): Location
    {
        if ($this->locationRepository->findByZoneAndCode($zoneId, $data->code) !== null) {
            throw new \DomainException("Ya existe una ubicación con el código '{$data->code}' en esta zona.");
        }

        return $this->locationRepository->save(new Location(
            id: null,
            zoneId: $zoneId,
            name: $data->name,
            code: $data->code,
            volumeCm3: $data->volumeCm3,
            maxWeightKg: $data->maxWeightKg,
            description: $data->description,
        ));
    }
}
