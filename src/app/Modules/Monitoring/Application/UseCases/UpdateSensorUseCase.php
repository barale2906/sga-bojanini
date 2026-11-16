<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Application\DTOs\SensorData;
use App\Modules\Monitoring\Domain\Entities\Sensor;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\UserSensorRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\UserWarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UpdateSensorUseCase
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
        private readonly UserSensorRepositoryInterface $userSensorRepository,
        private readonly UserWarehouseRepositoryInterface $userWarehouseRepository,
    ) {}

    public function execute(int $id, SensorData $data): Sensor
    {
        $sensor = $this->sensorRepository->findById($id);
        if ($sensor === null) {
            throw new \DomainException('Sensor no encontrado.');
        }

        $zone = $this->zoneRepository->findById($data->zoneId);
        if ($zone === null) {
            throw new \DomainException('La zona indicada no existe.');
        }

        $existing = $this->sensorRepository->findByCode($data->code);
        if ($existing !== null && $existing->getId() !== $id) {
            throw new \DomainException("Ya existe un sensor con el código '{$data->code}'.");
        }

        $previousWarehouseId = $this->zoneRepository->findById($sensor->getZoneId())?->getWarehouseId();

        $updated = new Sensor(
            id: $id,
            zoneId: $data->zoneId,
            code: $data->code,
            name: $data->name,
            type: $data->type,
            unit: $data->unit,
            isActive: $data->isActive ?? $sensor->isActive(),
        );

        return DB::transaction(function () use ($updated, $zone, $previousWarehouseId, $id) {
            $saved = $this->sensorRepository->save($updated);

            // Los usuarios del sensor se derivan de su almacén: si cambia de
            // almacén, se reasigna a los usuarios del nuevo almacén.
            if ($previousWarehouseId !== $zone->getWarehouseId()) {
                $this->userSensorRepository->syncUsersForSensor(
                    $id,
                    $this->userWarehouseRepository->findUserIdsByWarehouse($zone->getWarehouseId()),
                );
                $this->userSensorRepository->assignSensorToUsersWithRole($id, 'super_administrador');
            }

            return $saved;
        });
    }
}
