<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Application\DTOs\SensorData;
use App\Modules\Monitoring\Domain\Entities\Sensor;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\UserSensorRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\UserWarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class CreateSensorUseCase
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
        private readonly UserSensorRepositoryInterface $userSensorRepository,
        private readonly UserWarehouseRepositoryInterface $userWarehouseRepository,
    ) {}

    public function execute(SensorData $data): Sensor
    {
        $zone = $this->zoneRepository->findById($data->zoneId);

        if ($zone === null) {
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

        $saved = $this->sensorRepository->save($sensor);

        // Asignar al super_administrador siempre.
        $this->userSensorRepository->assignSensorToUsersWithRole($saved->getId(), 'super_administrador');

        // Asignar a los usuarios que ya tienen el almacén de esta zona asignado.
        $userIds = $this->userWarehouseRepository->findUserIdsByWarehouse($zone->getWarehouseId());
        $this->userSensorRepository->assignSensorToUsers($saved->getId(), $userIds);

        return $saved;
    }
}
