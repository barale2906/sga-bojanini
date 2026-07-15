<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\ProductVariant;
use App\Modules\Catalog\Domain\Repositories\ProductVariantRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;

class EloquentProductVariantRepository implements ProductVariantRepositoryInterface
{
    public function findById(int $id): ?ProductVariant
    {
        $model = ProductVariantModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByGenericProduct(int $genericProductId): array
    {
        return ProductVariantModel::where('generic_product_id', $genericProductId)
            ->orderBy('lab_brand')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function save(ProductVariant $variant): ProductVariant
    {
        $model = $variant->getId()
            ? ProductVariantModel::findOrFail($variant->getId())
            : new ProductVariantModel();

        $model->generic_product_id      = $variant->getGenericProductId();
        $model->lab_brand               = $variant->getLabBrand();
        $model->brand_sku               = $variant->getBrandSku();
        $model->commercial_presentation = $variant->getCommercialPresentation();
        $model->serie_reference         = $variant->getSerieReference();
        $model->useful_life             = $variant->getUsefulLife();
        $model->risk_level              = $variant->getRiskLevel();
        $model->is_active               = $variant->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ProductVariantModel::findOrFail($id)->delete();
    }

    private function toDomain(ProductVariantModel $model): ProductVariant
    {
        return new ProductVariant(
            id:                     $model->id,
            genericProductId:       $model->generic_product_id,
            labBrand:               $model->lab_brand,
            brandSku:               $model->brand_sku,
            commercialPresentation: $model->commercial_presentation,
            serieReference:         $model->serie_reference,
            usefulLife:             $model->useful_life,
            riskLevel:              $model->risk_level,
            isActive:               (bool) $model->is_active,
        );
    }
}
