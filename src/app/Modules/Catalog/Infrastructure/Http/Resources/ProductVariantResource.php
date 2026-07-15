<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Resources;

use App\Modules\Catalog\Domain\Entities\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variant = $this->resource;

        if ($variant instanceof ProductVariant) {
            return [
                'id'                      => $variant->getId(),
                'generic_product_id'      => $variant->getGenericProductId(),
                'lab_brand'               => $variant->getLabBrand(),
                'brand_sku'               => $variant->getBrandSku(),
                'commercial_presentation' => $variant->getCommercialPresentation(),
                'serie_reference'         => $variant->getSerieReference(),
                'useful_life'             => $variant->getUsefulLife(),
                'risk_level'              => $variant->getRiskLevel(),
                'is_active'               => $variant->isActive(),
            ];
        }

        // Eloquent model fallback
        return [
            'id'                      => $variant->id,
            'generic_product_id'      => $variant->generic_product_id,
            'lab_brand'               => $variant->lab_brand,
            'brand_sku'               => $variant->brand_sku,
            'commercial_presentation' => $variant->commercial_presentation,
            'serie_reference'         => $variant->serie_reference,
            'useful_life'             => $variant->useful_life,
            'risk_level'              => $variant->risk_level,
            'is_active'               => (bool) $variant->is_active,
            'sanitary_registrations'  => $this->whenLoaded('sanitaryRegistrations', fn () => $variant->sanitaryRegistrations->map(fn ($r) => [
                'id'                  => $r->id,
                'registration_number' => $r->registration_number,
                'expiry_date'         => $r->expiry_date?->format('Y-m-d'),
                'is_active'           => (bool) $r->is_active,
                'is_expired'          => $r->expiry_date !== null && $r->expiry_date->isPast(),
            ])),
            'created_at'              => $variant->created_at?->toIso8601String(),
        ];
    }
}
