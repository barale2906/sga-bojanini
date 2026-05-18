<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Repositories;

use App\Modules\Monitoring\Domain\Entities\SensorReading;
use Carbon\Carbon;

interface SensorReadingRepositoryInterface
{
    public function findById(int $id): ?SensorReading;

    public function save(SensorReading $reading): SensorReading;

    /**
     * @return SensorReading[]
     */
    public function findBySensorAndDateRange(int $sensorId, Carbon $from, Carbon $to): array;

    /**
     * @return SensorReading[]
     */
    public function findLastNBySensor(int $sensorId, int $n): array;
}
