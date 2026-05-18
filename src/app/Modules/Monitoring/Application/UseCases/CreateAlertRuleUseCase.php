<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Application\DTOs\AlertRuleData;
use App\Modules\Monitoring\Domain\Entities\AlertRule;
use App\Modules\Monitoring\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;

class CreateAlertRuleUseCase
{
    public function __construct(
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
        private readonly SensorRepositoryInterface $sensorRepository,
    ) {}

    public function execute(AlertRuleData $data): AlertRule
    {
        if ($this->sensorRepository->findById($data->sensorId) === null) {
            throw new \DomainException('El sensor indicado no existe.');
        }

        $rule = new AlertRule(
            id: null,
            sensorId: $data->sensorId,
            conditionType: $data->conditionType,
            threshold: $data->threshold,
            consecutiveReadings: $data->consecutiveReadings,
            notificationChannels: $data->notificationChannels,
            isActive: $data->isActive,
        );

        return $this->alertRuleRepository->save($rule);
    }
}
