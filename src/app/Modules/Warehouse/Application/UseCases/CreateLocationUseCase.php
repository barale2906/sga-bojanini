<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Application\DTOs\LocationData;
use App\Modules\Warehouse\Domain\Entities\Location;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class CreateLocationUseCase
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
    ) {}

    public function execute(LocationData $data): Location
    {
        if ($this->zoneRepository->findById($data->zoneId) === null) {
            throw new \DomainException('La zona indicada no existe.');
        }

        if ($this->locationRepository->findByZoneAndCode($data->zoneId, $data->code) !== null) {
            throw new \DomainException("Ya existe una ubicación con el código '{$data->code}' en esta zona.");
        }

        $location = new Location(
            id:          null,
            zoneId:      $data->zoneId,
            name:        $data->name,
            code:        $data->code,
            volumeCm3:   $data->volumeCm3,
            maxWeightKg: $data->maxWeightKg,
            description: $data->description,
        );

        return $this->locationRepository->save($location);
    }
}
