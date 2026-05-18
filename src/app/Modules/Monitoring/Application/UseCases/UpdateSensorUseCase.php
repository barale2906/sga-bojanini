<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Application\DTOs\SensorData;
use App\Modules\Monitoring\Domain\Entities\Sensor;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class UpdateSensorUseCase
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
    ) {}

    public function execute(int $id, SensorData $data): Sensor
    {
        $sensor = $this->sensorRepository->findById($id);
        if ($sensor === null) {
            throw new \DomainException('Sensor no encontrado.');
        }

        if ($this->zoneRepository->findById($data->zoneId) === null) {
            throw new \DomainException('La zona indicada no existe.');
        }

        $existing = $this->sensorRepository->findByCode($data->code);
        if ($existing !== null && $existing->getId() !== $id) {
            throw new \DomainException("Ya existe un sensor con el código '{$data->code}'.");
        }

        $updated = new Sensor(
            id: $id,
            zoneId: $data->zoneId,
            code: $data->code,
            name: $data->name,
            type: $data->type,
            unit: $data->unit,
            isActive: $sensor->isActive(),
        );

        return $this->sensorRepository->save($updated);
    }
}
