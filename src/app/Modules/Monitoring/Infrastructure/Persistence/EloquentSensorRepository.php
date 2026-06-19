<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Persistence;

use App\Modules\Monitoring\Domain\Entities\Sensor;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;

class EloquentSensorRepository implements SensorRepositoryInterface
{
    public function findById(int $id): ?Sensor
    {
        $model = SensorModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?Sensor
    {
        $model = SensorModel::where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = SensorModel::query();

        if (isset($filters['zone_id'])) {
            $query->where('zone_id', $filters['zone_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Restringe el listado a un subconjunto de IDs (control de acceso por
        // sensor). `null` significa "sin restricción".
        if (isset($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query->orderBy('code')->get()->map(fn ($m) => $this->toDomain($m))->all();
    }

    public function findAllActive(): array
    {
        return SensorModel::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->all();
    }

    public function save(Sensor $sensor): Sensor
    {
        $model = $sensor->getId()
            ? SensorModel::findOrFail($sensor->getId())
            : new SensorModel;

        $model->zone_id = $sensor->getZoneId();
        $model->code = $sensor->getCode();
        $model->name = $sensor->getName();
        $model->type = $sensor->getType();
        $model->unit = $sensor->getUnit();
        $model->is_active = $sensor->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        SensorModel::findOrFail($id)->delete();
    }

    private function toDomain(SensorModel $model): Sensor
    {
        return new Sensor(
            id: $model->id,
            zoneId: $model->zone_id,
            code: $model->code,
            name: $model->name,
            type: $model->type,
            unit: $model->unit,
            isActive: (bool) $model->is_active,
        );
    }
}
