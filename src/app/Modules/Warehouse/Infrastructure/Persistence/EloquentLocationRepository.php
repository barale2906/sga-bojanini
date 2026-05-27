<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence;

use App\Modules\Warehouse\Domain\Entities\Location;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;

class EloquentLocationRepository implements LocationRepositoryInterface
{
    public function findById(int $id): ?Location
    {
        $model = LocationModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = LocationModel::query();

        if (isset($filters['zone_id'])) {
            $query->where('zone_id', $filters['zone_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('code', 'like', "%{$filters['search']}%");
            });
        }

        return $query->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function findByZoneId(int $zoneId): array
    {
        return $this->findAll(['zone_id' => $zoneId]);
    }

    public function findByWarehouseId(int $warehouseId): array
    {
        return LocationModel::query()
            ->whereHas('zone', fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->get()
            ->map(fn ($model) => $this->toDomain($model))
            ->toArray();
    }

    public function findByZoneAndCode(int $zoneId, string $code): ?Location
    {
        $model = LocationModel::where('zone_id', $zoneId)
            ->where('code', $code)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Location $location): Location
    {
        $model = $location->getId()
            ? LocationModel::findOrFail($location->getId())
            : new LocationModel();

        $model->zone_id       = $location->getZoneId();
        $model->name          = $location->getName();
        $model->code          = $location->getCode();
        $model->volume_cm3    = $location->getVolumeCm3();
        $model->max_weight_kg = $location->getMaxWeightKg();
        $model->description   = $location->getDescription();
        $model->is_active     = $location->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        LocationModel::findOrFail($id)->delete();
    }

    private function toDomain(LocationModel $model): Location
    {
        return new Location(
            id:          $model->id,
            zoneId:      $model->zone_id,
            name:        $model->name,
            code:        $model->code,
            volumeCm3:   $model->volume_cm3    !== null ? (float) $model->volume_cm3    : null,
            maxWeightKg: $model->max_weight_kg !== null ? (float) $model->max_weight_kg : null,
            description: $model->description,
            isActive:    (bool) $model->is_active,
        );
    }
}
