<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;

class EloquentProductPresentationRepository implements ProductPresentationRepositoryInterface
{
    public function findById(int $id): ?ProductPresentation
    {
        $model = ProductPresentationModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(): array
    {
        return ProductPresentationModel::orderBy('level')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function findByProductId(int $productId): array
    {
        $product = ProductModel::find($productId);

        if ($product === null) {
            return [];
        }

        return $product->presentations()
            ->orderByPivot('sort_order')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function save(ProductPresentation $presentation): ProductPresentation
    {
        $model = $presentation->getId()
            ? ProductPresentationModel::findOrFail($presentation->getId())
            : new ProductPresentationModel();

        $model->parent_id           = $presentation->getParentId();
        $model->name                = $presentation->getName();
        $model->code                = $presentation->getCode();
        $model->units_of_measure_id = $presentation->getUnitsOfMeasureId();
        $model->quantity_per_parent = $presentation->getQuantityPerParent();
        $model->factor_to_base      = $presentation->getFactorToBase();
        $model->level               = $presentation->getLevel();
        $model->is_active           = $presentation->isActive();
        $model->sort_order          = $presentation->getSortOrder();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ProductPresentationModel::findOrFail($id)->delete();
    }

    public function attachToProduct(
        int $presentationId,
        int $productId,
        bool $isPurchaseDefault = false,
        int $sortOrder = 0,
    ): void {
        $product = ProductModel::findOrFail($productId);

        if ($isPurchaseDefault) {
            // Desmarcar cualquier otra presentación default para este producto
            $product->presentations()->newPivotStatement()
                ->where('product_id', $productId)
                ->update(['is_purchase_default' => false]);
        }

        $product->presentations()->syncWithoutDetaching([
            $presentationId => [
                'is_purchase_default' => $isPurchaseDefault,
                'sort_order'          => $sortOrder,
            ],
        ]);
    }

    public function detachFromProduct(int $presentationId, int $productId): void
    {
        $product = ProductModel::findOrFail($productId);
        $product->presentations()->detach($presentationId);
    }

    public function setProductDefault(int $presentationId, int $productId): void
    {
        $product = ProductModel::findOrFail($productId);

        // Quitar el default anterior
        $product->presentations()->newPivotStatement()
            ->where('product_id', $productId)
            ->update(['is_purchase_default' => false]);

        // Establecer el nuevo default
        $product->presentations()->updateExistingPivot($presentationId, [
            'is_purchase_default' => true,
        ]);
    }

    private function toDomain(ProductPresentationModel $model): ProductPresentation
    {
        return new ProductPresentation(
            id: $model->id,
            parentId: $model->parent_id,
            name: $model->name,
            code: $model->code,
            unitsOfMeasureId: $model->units_of_measure_id,
            quantityPerParent: $model->quantity_per_parent,
            factorToBase: $model->factor_to_base,
            level: $model->level,
            isActive: (bool) $model->is_active,
            sortOrder: (int) $model->sort_order,
        );
    }
}
