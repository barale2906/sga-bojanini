<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;

class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?Product
    {
        $model = ProductModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByCode(string $code): ?Product
    {
        $model = ProductModel::where('code', $code)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = ProductModel::query();

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('code', 'like', "%{$filters['search']}%")
                    ->orWhere('sku', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('name')->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function save(Product $product): Product
    {
        $model = $product->getId()
            ? ProductModel::findOrFail($product->getId())
            : new ProductModel();

        $model->category_id             = $product->getCategoryId();
        $model->classification_id       = $product->getClassificationId();
        $model->base_unit_id            = $product->getBaseUnitId();
        $model->product_type            = $product->getProductType()->value;
        $model->name                    = $product->getName();
        $model->code                    = $product->getCode();
        $model->sku                     = $product->getSku();
        $model->description             = $product->getDescription();
        $model->volume_cm3              = $product->getVolumeCm3();
        $model->weight_kg               = $product->getWeightKg();
        $model->requires_cold_chain     = $product->requiresColdChain();
        $model->reorder_point           = $product->getReorderPoint();
        $model->reorder_quantity        = $product->getReorderQuantity();
        $model->min_stock               = $product->getMinStock();
        $model->max_stock               = $product->getMaxStock();
        $model->is_active               = $product->isActive();
        $model->concentration           = $product->getConcentration();
        $model->risk_level              = $product->getRiskLevel();
        $model->lab_brand               = $product->getLabBrand();
        $model->pharmaceutical_form     = $product->getPharmaceuticalForm();
        $model->commercial_presentation = $product->getCommercialPresentation();
        $model->serie_reference         = $product->getSerieReference();
        $model->useful_life             = $product->getUsefulLife();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ProductModel::findOrFail($id)->delete();
    }

    private function toDomain(ProductModel $model): Product
    {
        return new Product(
            id:                     $model->id,
            categoryId:             $model->category_id,
            baseUnitId:             $model->base_unit_id,
            productType:            ProductType::from($model->product_type),
            name:                   $model->name,
            code:                   $model->code,
            classificationId:       $model->classification_id,
            sku:                    $model->sku,
            description:            $model->description,
            volumeCm3:              $model->volume_cm3 !== null ? (float) $model->volume_cm3 : null,
            weightKg:               $model->weight_kg  !== null ? (float) $model->weight_kg  : null,
            requiresColdChain:      (bool) $model->requires_cold_chain,
            reorderPoint:           (int) $model->reorder_point,
            reorderQuantity:        (int) $model->reorder_quantity,
            minStock:               (int) $model->min_stock,
            maxStock:               (int) $model->max_stock,
            isActive:               (bool) $model->is_active,
            concentration:          $model->concentration,
            riskLevel:              $model->risk_level,
            labBrand:               $model->lab_brand,
            pharmaceuticalForm:     $model->pharmaceutical_form,
            commercialPresentation: $model->commercial_presentation,
            serieReference:         $model->serie_reference,
            usefulLife:             $model->useful_life,
        );
    }
}
