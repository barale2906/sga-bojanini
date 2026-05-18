<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\Category;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;

class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function findById(int $id): ?Category
    {
        $model = CategoryModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?Category
    {
        $model = CategoryModel::where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = CategoryModel::query();

        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
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

        return $query->orderBy('name')->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function save(Category $category): Category
    {
        $model = $category->getId()
            ? CategoryModel::findOrFail($category->getId())
            : new CategoryModel();

        $model->parent_id = $category->getParentId();
        $model->name = $category->getName();
        $model->code = $category->getCode();
        $model->description = $category->getDescription();
        $model->is_active = $category->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        CategoryModel::findOrFail($id)->delete();
    }

    private function toDomain(CategoryModel $model): Category
    {
        return new Category(
            id: $model->id,
            parentId: $model->parent_id,
            name: $model->name,
            code: $model->code,
            description: $model->description,
            isActive: (bool) $model->is_active,
        );
    }
}
