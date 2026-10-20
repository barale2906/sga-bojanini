<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence;

use App\Modules\Assets\Domain\Entities\AssetCategory;
use App\Modules\Assets\Domain\Repositories\AssetCategoryRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCategoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;

class EloquentAssetCategoryRepository implements AssetCategoryRepositoryInterface
{
    public function findById(int $id): ?AssetCategory
    {
        $model = AssetCategoryModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?AssetCategory
    {
        $model = AssetCategoryModel::where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = AssetCategoryModel::query();

        if (isset($filters['asset_type'])) {
            $query->where('asset_type', $filters['asset_type']);
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

        return $query->orderBy('name')->get()->map(fn ($m) => $this->toDomain($m))->all();
    }

    public function save(AssetCategory $category): AssetCategory
    {
        $model = $category->getId()
            ? AssetCategoryModel::findOrFail($category->getId())
            : new AssetCategoryModel();

        $model->fill([
            'code'        => $category->getCode(),
            'name'        => $category->getName(),
            'asset_type'  => $category->getAssetType(),
            'description' => $category->getDescription(),
            'is_active'   => $category->isActive(),
        ])->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        AssetCategoryModel::findOrFail($id)->delete();
    }

    public function hasAssets(int $id): bool
    {
        return AssetModel::withTrashed()->where('category_id', $id)->exists();
    }

    private function toDomain(AssetCategoryModel $model): AssetCategory
    {
        return new AssetCategory(
            id:          $model->id,
            code:        $model->code,
            name:        $model->name,
            assetType:   $model->asset_type,
            description: $model->description,
            isActive:    (bool) $model->is_active,
        );
    }
}
