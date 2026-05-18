<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence;

use App\Modules\Warehouse\Domain\Entities\Zone;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;

class EloquentZoneRepository implements ZoneRepositoryInterface
{
    public function findById(int $id): ?Zone
    {
        $model = ZoneModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = ZoneModel::query();

        if (isset($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('code', 'like', "%{$filters['search']}%");
            });
        }

        return $query->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function findByWarehouseId(int $warehouseId): array
    {
        return $this->findAll(['warehouse_id' => $warehouseId]);
    }

    public function findByWarehouseAndCode(int $warehouseId, string $code): ?Zone
    {
        $model = ZoneModel::where('warehouse_id', $warehouseId)
            ->where('code', $code)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Zone $zone): Zone
    {
        $model = $zone->getId()
            ? ZoneModel::findOrFail($zone->getId())
            : new ZoneModel();

        $model->warehouse_id = $zone->getWarehouseId();
        $model->name = $zone->getName();
        $model->code = $zone->getCode();
        $model->type = $zone->getType();
        $model->temp_min = $zone->getTempMin();
        $model->temp_max = $zone->getTempMax();
        $model->humidity_min = $zone->getHumidityMin();
        $model->humidity_max = $zone->getHumidityMax();
        $model->description = $zone->getDescription();
        $model->is_active = $zone->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ZoneModel::findOrFail($id)->delete();
    }

    private function toDomain(ZoneModel $model): Zone
    {
        return new Zone(
            id: $model->id,
            warehouseId: $model->warehouse_id,
            name: $model->name,
            code: $model->code,
            type: $model->type,
            tempMin: $model->temp_min !== null ? (float) $model->temp_min : null,
            tempMax: $model->temp_max !== null ? (float) $model->temp_max : null,
            humidityMin: $model->humidity_min !== null ? (float) $model->humidity_min : null,
            humidityMax: $model->humidity_max !== null ? (float) $model->humidity_max : null,
            description: $model->description,
            isActive: (bool) $model->is_active,
        );
    }
}
