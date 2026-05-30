<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence;

use App\Modules\CostCenter\Domain\Entities\MedicalService;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;

class EloquentMedicalServiceRepository implements MedicalServiceRepositoryInterface
{
    public function findById(int $id): ?MedicalService
    {
        $model = MedicalServiceModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?MedicalService
    {
        $model = MedicalServiceModel::where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = MedicalServiceModel::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('code', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('name')->get()->map(fn ($m) => $this->toDomain($m))->toArray();
    }

    public function save(MedicalService $service): MedicalService
    {
        $model = $service->getId()
            ? MedicalServiceModel::findOrFail($service->getId())
            : new MedicalServiceModel();

        $model->code        = $service->getCode();
        $model->name        = $service->getName();
        $model->description = $service->getDescription();
        $model->is_active   = $service->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        MedicalServiceModel::findOrFail($id)->delete();
    }

    private function toDomain(MedicalServiceModel $model): MedicalService
    {
        return new MedicalService(
            id:          $model->id,
            code:        $model->code,
            name:        $model->name,
            description: $model->description,
            isActive:    (bool) $model->is_active,
        );
    }
}
