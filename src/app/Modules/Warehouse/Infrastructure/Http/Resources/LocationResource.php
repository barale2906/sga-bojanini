<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Resources;

use App\Modules\Warehouse\Domain\Entities\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->resource;

        if ($location instanceof Location) {
            return [
                'id'            => $location->getId(),
                'zone_id'       => $location->getZoneId(),
                'name'          => $location->getName(),
                'code'          => $location->getCode(),
                'volume_cm3'    => $location->getVolumeCm3(),
                'max_weight_kg' => $location->getMaxWeightKg(),
                'description'   => $location->getDescription(),
                'is_active'     => $location->isActive(),
            ];
        }

        // Eloquent model fallback (listados directos desde el modelo)
        return [
            'id'            => $location->id,
            'zone_id'       => $location->zone_id,
            'name'          => $location->name,
            'code'          => $location->code,
            'volume_cm3'    => $location->volume_cm3 !== null ? (float) $location->volume_cm3 : null,
            'max_weight_kg' => $location->max_weight_kg !== null ? (float) $location->max_weight_kg : null,
            'description'   => $location->description,
            'is_active'     => (bool) $location->is_active,
            'created_at'    => $location->created_at?->toIso8601String(),
        ];
    }
}
