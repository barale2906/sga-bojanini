<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;

class ListAlertRulesUseCase
{
    public function __construct(
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
        private readonly SensorRepositoryInterface $sensorRepository,
    ) {}

    public function execute(int $sensorId): array
    {
        if ($this->sensorRepository->findById($sensorId) === null) {
            throw new \DomainException('Sensor no encontrado.');
        }

        return $this->alertRuleRepository->findBySensorId($sensorId);
    }
}
