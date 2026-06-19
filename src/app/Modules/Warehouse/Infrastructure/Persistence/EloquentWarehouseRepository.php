<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence;

use App\Modules\Warehouse\Domain\Entities\Warehouse;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;

class EloquentWarehouseRepository implements WarehouseRepositoryInterface
{
    public function findById(int $id): ?Warehouse
    {
        $model = WarehouseModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?Warehouse
    {
        $model = WarehouseModel::where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = WarehouseModel::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('code', 'like', "%{$filters['search']}%");
            });
        }

        // Restringe el listado a un subconjunto de IDs (control de acceso por
        // almacén). `null` significa "sin restricción".
        if (isset($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn('id', $filters['ids']);
        }

        return $query->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function save(Warehouse $warehouse): Warehouse
    {
        $model = $warehouse->getId()
            ? WarehouseModel::findOrFail($warehouse->getId())
            : new WarehouseModel();

        $model->name = $warehouse->getName();
        $model->code = $warehouse->getCode();
        $model->address = $warehouse->getAddress();
        $model->description = $warehouse->getDescription();
        $model->is_active = $warehouse->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        WarehouseModel::findOrFail($id)->delete();
    }

    private function toDomain(WarehouseModel $model): Warehouse
    {
        return new Warehouse(
            id: $model->id,
            name: $model->name,
            code: $model->code,
            address: $model->address,
            description: $model->description,
            isActive: (bool) $model->is_active,
        );
    }
}
