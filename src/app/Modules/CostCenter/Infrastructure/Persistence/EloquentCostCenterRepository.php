<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence;

use App\Modules\CostCenter\Domain\Entities\CostCenter;
use App\Modules\CostCenter\Domain\Enums\CostCenterType;
use App\Modules\CostCenter\Domain\Repositories\CostCenterRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;

class EloquentCostCenterRepository implements CostCenterRepositoryInterface
{
    public function findById(int $id): ?CostCenter
    {
        $model = CostCenterModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?CostCenter
    {
        $model = CostCenterModel::where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = CostCenterModel::query();

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('code', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('type')->orderBy('name')->get()->map(fn ($m) => $this->toDomain($m))->toArray();
    }

    public function save(CostCenter $costCenter): CostCenter
    {
        $model = $costCenter->getId()
            ? CostCenterModel::findOrFail($costCenter->getId())
            : new CostCenterModel();

        $model->code        = $costCenter->getCode();
        $model->name        = $costCenter->getName();
        $model->type        = $costCenter->getType()->value;
        $model->description = $costCenter->getDescription();
        $model->is_active   = $costCenter->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        CostCenterModel::findOrFail($id)->delete();
    }

    private function toDomain(CostCenterModel $model): CostCenter
    {
        return new CostCenter(
            id:          $model->id,
            code:        $model->code,
            name:        $model->name,
            type:        CostCenterType::from($model->type),
            description: $model->description,
            isActive:    (bool) $model->is_active,
        );
    }
}
