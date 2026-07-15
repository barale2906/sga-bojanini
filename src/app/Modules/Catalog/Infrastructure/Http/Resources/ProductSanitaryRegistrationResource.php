<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Resources;

use App\Modules\Catalog\Domain\Entities\ProductSanitaryRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSanitaryRegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $r = $this->resource;

        if ($r instanceof ProductSanitaryRegistration) {
            return [
                'id'                  => $r->getId(),
                'product_variant_id'  => $r->getProductVariantId(),
                'registration_number' => $r->getRegistrationNumber(),
                'expiry_date'         => $r->getExpiryDate()->format('Y-m-d'),
                'is_active'           => $r->isActive(),
                'is_expired'          => $r->isExpired(),
            ];
        }

        // Eloquent model fallback
        return [
            'id'                  => $r->id,
            'product_variant_id'  => $r->product_variant_id,
            'registration_number' => $r->registration_number,
            'expiry_date'         => $r->expiry_date?->format('Y-m-d'),
            'is_active'           => (bool) $r->is_active,
            'is_expired'          => $r->expiry_date !== null && $r->expiry_date->isPast(),
        ];
    }
}
