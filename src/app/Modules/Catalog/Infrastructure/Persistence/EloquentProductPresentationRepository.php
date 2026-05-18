<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;

class EloquentProductPresentationRepository implements ProductPresentationRepositoryInterface
{
    public function findById(int $id): ?ProductPresentation
    {
        $model = ProductPresentationModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByProductId(int $productId): array
    {
        return ProductPresentationModel::where('product_id', $productId)
            ->orderBy('level')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function save(ProductPresentation $presentation): ProductPresentation
    {
        $model = $presentation->getId()
            ? ProductPresentationModel::findOrFail($presentation->getId())
            : new ProductPresentationModel();

        $model->product_id = $presentation->getProductId();
        $model->parent_id = $presentation->getParentId();
        $model->name = $presentation->getName();
        $model->code = $presentation->getCode();
        $model->units_of_measure_id = $presentation->getUnitsOfMeasureId();
        $model->quantity_per_parent = $presentation->getQuantityPerParent();
        $model->factor_to_base = $presentation->getFactorToBase();
        $model->level = $presentation->getLevel();
        $model->is_purchase_default = $presentation->isPurchaseDefault();
        $model->is_active = $presentation->isActive();
        $model->sort_order = $presentation->getSortOrder();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ProductPresentationModel::findOrFail($id)->delete();
    }

    private function toDomain(ProductPresentationModel $model): ProductPresentation
    {
        return new ProductPresentation(
            id: $model->id,
            productId: $model->product_id,
            parentId: $model->parent_id,
            name: $model->name,
            code: $model->code,
            unitsOfMeasureId: $model->units_of_measure_id,
            quantityPerParent: $model->quantity_per_parent,
            factorToBase: $model->factor_to_base,
            level: $model->level,
            isPurchaseDefault: (bool) $model->is_purchase_default,
            isActive: (bool) $model->is_active,
            sortOrder: (int) $model->sort_order,
        );
    }
}
