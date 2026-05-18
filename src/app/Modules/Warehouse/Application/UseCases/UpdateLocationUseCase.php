<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Application\DTOs\LocationData;
use App\Modules\Warehouse\Domain\Entities\Location;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;

class UpdateLocationUseCase
{
    public function __construct(
        private readonly LocationRepositoryInterface $repository,
    ) {}

    public function execute(int $id, LocationData $data): Location
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Ubicación no encontrada.');
        }

        $duplicate = $this->repository->findByZoneAndCode($data->zoneId, $data->code);

        if ($duplicate !== null && $duplicate->getId() !== $id) {
            throw new \DomainException("Ya existe una ubicación con el código '{$data->code}' en esta zona.");
        }

        $location = new Location(
            id: $id,
            zoneId: $data->zoneId,
            name: $data->name,
            code: $data->code,
            capacity: $data->capacity,
            description: $data->description,
            isActive: $existing->isActive(),
        );

        return $this->repository->save($location);
    }
}
