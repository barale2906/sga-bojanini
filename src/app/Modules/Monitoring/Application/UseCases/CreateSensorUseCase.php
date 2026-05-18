<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Application\DTOs\SensorData;
use App\Modules\Monitoring\Domain\Entities\Sensor;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class CreateSensorUseCase
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
    ) {}

    public function execute(SensorData $data): Sensor
    {
        if ($this->zoneRepository->findById($data->zoneId) === null) {
            throw new \DomainException('La zona indicada no existe.');
        }

        if ($this->sensorRepository->findByCode($data->code) !== null) {
            throw new \DomainException("Ya existe un sensor con el código '{$data->code}'.");
        }

        $sensor = new Sensor(
            id: null,
            zoneId: $data->zoneId,
            code: $data->code,
            name: $data->name,
            type: $data->type,
            unit: $data->unit,
        );

        return $this->sensorRepository->save($sensor);
    }
}
