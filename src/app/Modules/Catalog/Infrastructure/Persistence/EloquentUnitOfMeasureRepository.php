<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\UnitOfMeasure;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;

class EloquentUnitOfMeasureRepository implements UnitOfMeasureRepositoryInterface
{
    public function findById(int $id): ?UnitOfMeasure
    {
        $model = UnitOfMeasureModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = UnitOfMeasureModel::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['is_base'])) {
            $query->where('is_base', $filters['is_base']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('abbreviation', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('name')->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function save(UnitOfMeasure $unitOfMeasure): UnitOfMeasure
    {
        $model = $unitOfMeasure->getId()
            ? UnitOfMeasureModel::findOrFail($unitOfMeasure->getId())
            : new UnitOfMeasureModel();

        $model->name = $unitOfMeasure->getName();
        $model->abbreviation = $unitOfMeasure->getAbbreviation();
        $model->is_active = $unitOfMeasure->isActive();
        $model->is_base = $unitOfMeasure->isBase();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        UnitOfMeasureModel::findOrFail($id)->delete();
    }

    private function toDomain(UnitOfMeasureModel $model): UnitOfMeasure
    {
        return new UnitOfMeasure(
            id: $model->id,
            name: $model->name,
            abbreviation: $model->abbreviation,
            isActive: (bool) $model->is_active,
            isBase: (bool) $model->is_base,
        );
    }
}
