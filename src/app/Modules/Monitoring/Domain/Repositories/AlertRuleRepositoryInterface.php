<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Repositories;

use App\Modules\Monitoring\Domain\Entities\AlertRule;

interface AlertRuleRepositoryInterface
{
    public function findById(int $id): ?AlertRule;

    /**
     * @return AlertRule[]
     */
    public function findActiveBySensorId(int $sensorId): array;

    /**
     * @return AlertRule[]
     */
    public function findBySensorId(int $sensorId): array;

    public function save(AlertRule $rule): AlertRule;

    public function delete(int $id): void;
}
