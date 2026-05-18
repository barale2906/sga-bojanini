<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Persistence;

use App\Modules\Monitoring\Domain\Entities\SensorReading;
use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorReadingModel;
use Carbon\Carbon;
use DateTimeImmutable;

class EloquentSensorReadingRepository implements SensorReadingRepositoryInterface
{
    public function findById(int $id): ?SensorReading
    {
        $model = SensorReadingModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(SensorReading $reading): SensorReading
    {
        $model = $reading->getId()
            ? SensorReadingModel::findOrFail($reading->getId())
            : new SensorReadingModel();

        $model->sensor_id = $reading->getSensorId();
        $model->value = $reading->getValue();
        $model->reading_source = $reading->getReadingSource();
        $model->recorded_at = $reading->getRecordedAt()->format('Y-m-d H:i:s');
        $model->user_id = $reading->getUserId();
        $model->save();

        return $this->toDomain($model);
    }

    public function findBySensorAndDateRange(int $sensorId, Carbon $from, Carbon $to): array
    {
        return SensorReadingModel::where('sensor_id', $sensorId)
            ->whereBetween('recorded_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('recorded_at')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function findLastNBySensor(int $sensorId, int $n): array
    {
        return SensorReadingModel::where('sensor_id', $sensorId)
            ->orderByDesc('recorded_at')
            ->limit($n)
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    private function toDomain(SensorReadingModel $model): SensorReading
    {
        return new SensorReading(
            id: $model->id,
            sensorId: $model->sensor_id,
            value: (float) $model->value,
            readingSource: $model->reading_source,
            recordedAt: new DateTimeImmutable($model->recorded_at->format('Y-m-d H:i:s')),
            userId: $model->user_id,
        );
    }
}
