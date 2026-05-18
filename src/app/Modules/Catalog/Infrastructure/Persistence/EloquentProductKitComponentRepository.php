<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\ProductKitComponent;
use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductKitComponentModel;

class EloquentProductKitComponentRepository implements ProductKitComponentRepositoryInterface
{
    public function findByKitProductId(int $kitProductId, bool $activeOnly = true): array
    {
        $query = ProductKitComponentModel::where('kit_product_id', $kitProductId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function save(ProductKitComponent $component): ProductKitComponent
    {
        $model = $component->getId()
            ? ProductKitComponentModel::findOrFail($component->getId())
            : new ProductKitComponentModel();

        $model->kit_product_id = $component->getKitProductId();
        $model->component_product_id = $component->getComponentProductId();
        $model->quantity_per_kit = $component->getQuantityPerKit();
        $model->sort_order = $component->getSortOrder();
        $model->notes = $component->getNotes();
        $model->is_active = $component->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function deleteByKitProductId(int $kitProductId): void
    {
        ProductKitComponentModel::where('kit_product_id', $kitProductId)->delete();
    }

    private function toDomain(ProductKitComponentModel $model): ProductKitComponent
    {
        return new ProductKitComponent(
            id: $model->id,
            kitProductId: $model->kit_product_id,
            componentProductId: $model->component_product_id,
            quantityPerKit: $model->quantity_per_kit,
            sortOrder: (int) $model->sort_order,
            notes: $model->notes,
            isActive: (bool) $model->is_active,
        );
    }
}
