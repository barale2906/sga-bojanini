<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\ProductClassification;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;

class EloquentProductClassificationRepository implements ProductClassificationRepositoryInterface
{
    public function findById(int $id): ?ProductClassification
    {
        $model = ProductClassificationModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?ProductClassification
    {
        $model = ProductClassificationModel::where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = ProductClassificationModel::query();

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

    public function save(ProductClassification $classification): ProductClassification
    {
        $model = $classification->getId()
            ? ProductClassificationModel::findOrFail($classification->getId())
            : new ProductClassificationModel();

        $model->code                      = $classification->getCode();
        $model->name                      = $classification->getName();
        $model->description               = $classification->getDescription();
        $model->has_sanitary_registration = $classification->hasSanitaryRegistration();
        $model->has_concentration         = $classification->hasConcentration();
        $model->has_risk_level            = $classification->hasRiskLevel();
        $model->has_pharma_fields         = $classification->hasPharmaFields();
        $model->has_device_fields         = $classification->hasDeviceFields();
        $model->has_lab_brand             = $classification->hasLabBrand();
        $model->is_active                 = $classification->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ProductClassificationModel::findOrFail($id)->delete();
    }

    private function toDomain(ProductClassificationModel $model): ProductClassification
    {
        return new ProductClassification(
            id:                      $model->id,
            code:                    $model->code,
            name:                    $model->name,
            description:             $model->description,
            hasSanitaryRegistration: (bool) $model->has_sanitary_registration,
            hasConcentration:        (bool) $model->has_concentration,
            hasRiskLevel:            (bool) $model->has_risk_level,
            hasPharmaFields:         (bool) $model->has_pharma_fields,
            hasDeviceFields:         (bool) $model->has_device_fields,
            hasLabBrand:             (bool) $model->has_lab_brand,
            isActive:                (bool) $model->is_active,
        );
    }
}
