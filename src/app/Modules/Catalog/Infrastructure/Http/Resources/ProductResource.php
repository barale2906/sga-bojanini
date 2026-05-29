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
                'id'                     => $product->getId(),
                'category_id'            => $product->getCategoryId(),
                'classification_id'      => $product->getClassificationId(),
                'base_unit_id'           => $product->getBaseUnitId(),
                'product_type'           => $product->getProductType()->value,
                'name'                   => $product->getName(),
                'code'                   => $product->getCode(),
                'sku'                    => $product->getSku(),
                'description'            => $product->getDescription(),
                'volume_cm3'             => $product->getVolumeCm3(),
                'weight_kg'              => $product->getWeightKg(),
                'requires_cold_chain'    => $product->requiresColdChain(),
                'reorder_point'          => $product->getReorderPoint(),
                'reorder_quantity'       => $product->getReorderQuantity(),
                'min_stock'              => $product->getMinStock(),
                'max_stock'              => $product->getMaxStock(),
                'is_active'              => $product->isActive(),
                'concentration'          => $product->getConcentration(),
                'risk_level'             => $product->getRiskLevel(),
                'lab_brand'              => $product->getLabBrand(),
                'pharmaceutical_form'    => $product->getPharmaceuticalForm(),
                'commercial_presentation'=> $product->getCommercialPresentation(),
                'serie_reference'        => $product->getSerieReference(),
                'useful_life'            => $product->getUsefulLife(),
            ];
        }

        // Eloquent model fallback (show con relaciones cargadas)
        return [
            'id'                     => $product->id,
            'name'                   => $product->name,
            'code'                   => $product->code,
            'sku'                    => $product->sku,
            'description'            => $product->description,
            'product_type'           => $product->product_type,
            'volume_cm3'             => $product->volume_cm3 !== null ? (float) $product->volume_cm3 : null,
            'weight_kg'              => $product->weight_kg  !== null ? (float) $product->weight_kg  : null,
            'requires_cold_chain'    => (bool) $product->requires_cold_chain,
            'reorder_point'          => $product->reorder_point,
            'reorder_quantity'       => $product->reorder_quantity,
            'min_stock'              => $product->min_stock,
            'max_stock'              => $product->max_stock,
            'is_active'              => (bool) $product->is_active,
            'concentration'          => $product->concentration,
            'risk_level'             => $product->risk_level,
            'lab_brand'              => $product->lab_brand,
            'pharmaceutical_form'    => $product->pharmaceutical_form,
            'commercial_presentation'=> $product->commercial_presentation,
            'serie_reference'        => $product->serie_reference,
            'useful_life'            => $product->useful_life,
            'classification'         => $this->whenLoaded('classification', fn () => $product->classification ? [
                'id'                        => $product->classification->id,
                'code'                      => $product->classification->code,
                'name'                      => $product->classification->name,
                'has_sanitary_registration' => (bool) $product->classification->has_sanitary_registration,
                'has_concentration'         => (bool) $product->classification->has_concentration,
                'has_risk_level'            => (bool) $product->classification->has_risk_level,
                'has_pharma_fields'         => (bool) $product->classification->has_pharma_fields,
                'has_device_fields'         => (bool) $product->classification->has_device_fields,
                'has_lab_brand'             => (bool) $product->classification->has_lab_brand,
            ] : null),
            'category'               => $this->whenLoaded('category', fn () => [
                'id'   => $product->category->id,
                'name' => $product->category->name,
                'code' => $product->category->code,
            ]),
            'base_unit'              => $this->whenLoaded('baseUnit', fn () => [
                'id'           => $product->baseUnit->id,
                'name'         => $product->baseUnit->name,
                'abbreviation' => $product->baseUnit->abbreviation,
            ]),
            'sanitary_registrations' => $this->whenLoaded('sanitaryRegistrations', fn () => $product->sanitaryRegistrations->map(fn ($r) => [
                'id'                  => $r->id,
                'registration_number' => $r->registration_number,
                'expiry_date'         => $r->expiry_date?->format('Y-m-d'),
                'is_active'           => (bool) $r->is_active,
                'is_expired'          => $r->expiry_date !== null && $r->expiry_date->isPast(),
            ])),
            'components'             => $this->whenLoaded('kitComponents', fn () => $product->kitComponents->map(fn ($c) => [
                'id'                   => $c->id,
                'component_product_id' => $c->component_product_id,
                'quantity_per_kit'     => $c->quantity_per_kit,
                'sort_order'           => $c->sort_order,
                'component'            => $c->relationLoaded('componentProduct') ? [
                    'id'   => $c->componentProduct->id,
                    'name' => $c->componentProduct->name,
                    'code' => $c->componentProduct->code,
                ] : null,
            ])),
            'created_at'             => $product->created_at?->toIso8601String(),
        ];
    }
}
