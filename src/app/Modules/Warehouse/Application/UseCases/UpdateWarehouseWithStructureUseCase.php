<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Application\DTOs\UpsertLocationData;
use App\Modules\Warehouse\Application\DTOs\UpsertZoneData;
use App\Modules\Warehouse\Application\DTOs\WarehouseStructureData;
use App\Modules\Warehouse\Domain\Entities\Location;
use App\Modules\Warehouse\Domain\Entities\Warehouse;
use App\Modules\Warehouse\Domain\Entities\Zone;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class UpdateWarehouseWithStructureUseCase
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouseRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
        private readonly LocationRepositoryInterface $locationRepository,
    ) {}

    public function execute(int $id, WarehouseStructureData $data): Warehouse
    {
        $existing = $this->warehouseRepository->findById($id);
        if ($existing === null) {
            throw new \DomainException('Almacén no encontrado.');
        }

        $duplicate = $this->warehouseRepository->findByCode($data->code);
        if ($duplicate !== null && $duplicate->getId() !== $id) {
            throw new \DomainException("Ya existe un almacén con el código '{$data->code}'.");
        }

        $warehouse = $this->warehouseRepository->save(new Warehouse(
            id: $id,
            name: $data->name,
            code: $data->code,
            address: $data->address,
            description: $data->description,
            isActive: $existing->isActive(),
        ));

        foreach ($data->zones as $zoneData) {
            $zone = $zoneData->id !== null
                ? $this->updateZone($id, $zoneData)
                : $this->createZone($id, $zoneData);

            foreach ($zoneData->locations as $locationData) {
                if ($locationData->id !== null) {
                    $this->updateLocation($zone->getId(), $locationData);
                } else {
                    $this->createLocation($zone->getId(), $locationData);
                }
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

    private function updateZone(int $warehouseId, UpsertZoneData $data): Zone
    {
        $existing = $this->zoneRepository->findById($data->id);
        if ($existing === null) {
            throw new \DomainException("La zona con id {$data->id} no existe.");
        }

        if ($existing->getWarehouseId() !== $warehouseId) {
            throw new \DomainException("La zona con id {$data->id} no pertenece a este almacén.");
        }

        $duplicate = $this->zoneRepository->findByWarehouseAndCode($warehouseId, $data->code);
        if ($duplicate !== null && $duplicate->getId() !== $data->id) {
            throw new \DomainException("Ya existe una zona con el código '{$data->code}' en este almacén.");
        }

        return $this->zoneRepository->save(new Zone(
            id: $data->id,
            warehouseId: $warehouseId,
            name: $data->name,
            code: $data->code,
            type: $data->type,
            tempMin: $data->tempMin,
            tempMax: $data->tempMax,
            humidityMin: $data->humidityMin,
            humidityMax: $data->humidityMax,
            description: $data->description,
            isActive: $existing->isActive(),
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

    private function updateLocation(int $zoneId, UpsertLocationData $data): Location
    {
        $existing = $this->locationRepository->findById($data->id);
        if ($existing === null) {
            throw new \DomainException("La ubicación con id {$data->id} no existe.");
        }

        if ($existing->getZoneId() !== $zoneId) {
            throw new \DomainException("La ubicación con id {$data->id} no pertenece a esta zona.");
        }

        $duplicate = $this->locationRepository->findByZoneAndCode($zoneId, $data->code);
        if ($duplicate !== null && $duplicate->getId() !== $data->id) {
            throw new \DomainException("Ya existe una ubicación con el código '{$data->code}' en esta zona.");
        }

        return $this->locationRepository->save(new Location(
            id: $data->id,
            zoneId: $zoneId,
            name: $data->name,
            code: $data->code,
            volumeCm3: $data->volumeCm3,
            maxWeightKg: $data->maxWeightKg,
            description: $data->description,
            isActive: $existing->isActive(),
        ));
    }
}
