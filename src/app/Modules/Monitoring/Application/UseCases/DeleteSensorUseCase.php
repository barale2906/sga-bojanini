<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;

class DeleteSensorUseCase
{
    public function __construct(
        private readonly SensorRepositoryInterface $sensorRepository,
    ) {}

    public function execute(int $id): void
    {
        $sensor = $this->sensorRepository->findById($id);
        if ($sensor === null) {
            throw new \DomainException('Sensor no encontrado.');
        }

        $sensor->deactivate();
        $this->sensorRepository->save($sensor);
    }
}
