<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\GenericProduct;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use Illuminate\Support\Facades\DB;

class EloquentGenericProductRepository implements GenericProductRepositoryInterface
{
    public function findById(int $id): ?GenericProduct
    {
        $model = GenericProductModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByBarcode(string $barcode): ?GenericProduct
    {
        $model = GenericProductModel::where('barcode', $barcode)->first();

        if ($model === null) {
            $model = GenericProductModel::whereHas(
                'variants',
                fn ($q) => $q->where('brand_sku', $barcode)
            )->first();
        }

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = GenericProductModel::query();

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('barcode', 'like', "%{$filters['search']}%")
                    ->orWhereHas('variants', fn ($v) => $v->where('brand_sku', 'like', "%{$filters['search']}%"));
            });
        }

        return $query->orderBy('name')->get()->map(fn ($m) => $this->toDomain($m))->toArray();
    }

    public function save(GenericProduct $product): GenericProduct
    {
        $model = $product->getId()
            ? GenericProductModel::findOrFail($product->getId())
            : new GenericProductModel();

        $model->category_id        = $product->getCategoryId();
        $model->classification_id  = $product->getClassificationId();
        $model->base_unit_id       = $product->getBaseUnitId();
        $model->product_type       = $product->getProductType()->value;
        $model->name               = $product->getName();
        $model->barcode            = $product->getBarcode();
        $model->description        = $product->getDescription();
        $model->concentration      = $product->getConcentration();
        $model->pharmaceutical_form = $product->getPharmaceuticalForm();
        $model->volume_cm3         = $product->getVolumeCm3();
        $model->weight_kg          = $product->getWeightKg();
        $model->requires_cold_chain = $product->requiresColdChain();
        $model->reorder_point      = $product->getReorderPoint();
        $model->reorder_quantity   = $product->getReorderQuantity();
        $model->min_stock          = $product->getMinStock();
        $model->max_stock          = $product->getMaxStock();
        $model->is_active          = $product->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        GenericProductModel::findOrFail($id)->delete();
    }

    public function getMaxBarcode(): int
    {
        return (int) DB::table('product_generics')->max('barcode');
    }

    private function toDomain(GenericProductModel $model): GenericProduct
    {
        return new GenericProduct(
            id:                $model->id,
            categoryId:        $model->category_id,
            baseUnitId:        $model->base_unit_id,
            productType:       ProductType::from($model->product_type),
            name:              $model->name,
            barcode:           $model->barcode,
            classificationId:  $model->classification_id,
            description:       $model->description,
            concentration:     $model->concentration,
            pharmaceuticalForm: $model->pharmaceutical_form,
            volumeCm3:         $model->volume_cm3 !== null ? (float) $model->volume_cm3 : null,
            weightKg:          $model->weight_kg  !== null ? (float) $model->weight_kg  : null,
            requiresColdChain: (bool) $model->requires_cold_chain,
            reorderPoint:      (int) $model->reorder_point,
            reorderQuantity:   (int) $model->reorder_quantity,
            minStock:          (int) $model->min_stock,
            maxStock:          (int) $model->max_stock,
            isActive:          (bool) $model->is_active,
        );
    }
}
