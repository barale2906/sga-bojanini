<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\UseCases;

use App\Modules\Monitoring\Application\DTOs\AlertRuleData;
use App\Modules\Monitoring\Domain\Entities\AlertRule;
use App\Modules\Monitoring\Domain\Repositories\AlertRuleRepositoryInterface;

class UpdateAlertRuleUseCase
{
    public function __construct(
        private readonly AlertRuleRepositoryInterface $alertRuleRepository,
    ) {}

    public function execute(int $id, AlertRuleData $data): AlertRule
    {
        $existing = $this->alertRuleRepository->findById($id);
        if ($existing === null) {
            throw new \DomainException('Regla de alerta no encontrada.');
        }

        $rule = new AlertRule(
            id: $id,
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
