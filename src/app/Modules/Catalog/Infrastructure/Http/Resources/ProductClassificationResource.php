<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Resources;

use App\Modules\Catalog\Domain\Entities\ProductClassification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductClassificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $c = $this->resource;

        if ($c instanceof ProductClassification) {
            return [
                'id'                        => $c->getId(),
                'code'                      => $c->getCode(),
                'name'                      => $c->getName(),
                'description'               => $c->getDescription(),
                'has_sanitary_registration' => $c->hasSanitaryRegistration(),
                'has_concentration'         => $c->hasConcentration(),
                'has_risk_level'            => $c->hasRiskLevel(),
                'has_pharma_fields'         => $c->hasPharmaFields(),
                'has_device_fields'         => $c->hasDeviceFields(),
                'has_lab_brand'             => $c->hasLabBrand(),
                'is_active'                 => $c->isActive(),
            ];
        }

        // Eloquent model fallback
        return [
            'id'                        => $c->id,
            'code'                      => $c->code,
            'name'                      => $c->name,
            'description'               => $c->description,
            'has_sanitary_registration' => (bool) $c->has_sanitary_registration,
            'has_concentration'         => (bool) $c->has_concentration,
            'has_risk_level'            => (bool) $c->has_risk_level,
            'has_pharma_fields'         => (bool) $c->has_pharma_fields,
            'has_device_fields'         => (bool) $c->has_device_fields,
            'has_lab_brand'             => (bool) $c->has_lab_brand,
            'is_active'                 => (bool) $c->is_active,
        ];
    }
}
