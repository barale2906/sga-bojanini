<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Resources;

use App\Modules\Catalog\Domain\Entities\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->resource;

        if ($product instanceof Product) {
            return [
                'id'                  => $product->getId(),
                'category_id'         => $product->getCategoryId(),
                'base_unit_id'        => $product->getBaseUnitId(),
                'product_type'        => $product->getProductType()->value,
                'name'                => $product->getName(),
                'code'                => $product->getCode(),
                'sku'                 => $product->getSku(),
                'description'         => $product->getDescription(),
                'requires_cold_chain' => $product->requiresColdChain(),
                'reorder_point'       => $product->getReorderPoint(),
                'reorder_quantity'    => $product->getReorderQuantity(),
                'min_stock'           => $product->getMinStock(),
                'max_stock'           => $product->getMaxStock(),
                'is_active'           => $product->isActive(),
            ];
        }

        return [
            'id'                  => $product->id,
            'name'                => $product->name,
            'code'                => $product->code,
            'sku'                 => $product->sku,
            'description'         => $product->description,
            'product_type'        => $product->product_type,
            'requires_cold_chain' => (bool) $product->requires_cold_chain,
            'reorder_point'       => $product->reorder_point,
            'reorder_quantity'    => $product->reorder_quantity,
            'min_stock'           => $product->min_stock,
            'max_stock'           => $product->max_stock,
            'is_active'           => (bool) $product->is_active,
            'category'            => $this->whenLoaded('category', fn () => [
                'id'   => $product->category->id,
                'name' => $product->category->name,
                'code' => $product->category->code,
            ]),
            'base_unit'           => $this->whenLoaded('baseUnit', fn () => [
                'id'           => $product->baseUnit->id,
                'name'         => $product->baseUnit->name,
                'abbreviation' => $product->baseUnit->abbreviation,
            ]),
            'components'          => $this->whenLoaded('kitComponents', fn () => $product->kitComponents->map(fn ($c) => [
                'id'                    => $c->id,
                'component_product_id'  => $c->component_product_id,
                'quantity_per_kit'      => $c->quantity_per_kit,
                'sort_order'            => $c->sort_order,
                'component'             => $c->relationLoaded('componentProduct') ? [
                    'id'   => $c->componentProduct->id,
                    'name' => $c->componentProduct->name,
                    'code' => $c->componentProduct->code,
                ] : null,
            ])),
            'created_at'          => $product->created_at?->toIso8601String(),
        ];
    }
}
