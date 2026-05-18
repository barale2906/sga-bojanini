<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Persistence;

use App\Modules\Monitoring\Domain\Entities\AlertRule;
use App\Modules\Monitoring\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\AlertRuleModel;

class EloquentAlertRuleRepository implements AlertRuleRepositoryInterface
{
    public function findById(int $id): ?AlertRule
    {
        $model = AlertRuleModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findActiveBySensorId(int $sensorId): array
    {
        return AlertRuleModel::where('sensor_id', $sensorId)
            ->where('is_active', true)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function findBySensorId(int $sensorId): array
    {
        return AlertRuleModel::where('sensor_id', $sensorId)
            ->orderBy('id')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function save(AlertRule $rule): AlertRule
    {
        $model = $rule->getId()
            ? AlertRuleModel::findOrFail($rule->getId())
            : new AlertRuleModel();

        $model->sensor_id = $rule->getSensorId();
        $model->condition_type = $rule->getConditionType();
        $model->threshold = $rule->getThreshold();
        $model->consecutive_readings = $rule->getConsecutiveReadings();
        $model->notification_channels = $rule->getNotificationChannels();
        $model->is_active = $rule->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        AlertRuleModel::findOrFail($id)->delete();
    }

    private function toDomain(AlertRuleModel $model): AlertRule
    {
        return new AlertRule(
            id: $model->id,
            sensorId: $model->sensor_id,
            conditionType: $model->condition_type,
            threshold: $model->threshold !== null ? (float) $model->threshold : null,
            consecutiveReadings: (int) $model->consecutive_readings,
            notificationChannels: $model->notification_channels ?? ['internal'],
            isActive: (bool) $model->is_active,
        );
    }
}
