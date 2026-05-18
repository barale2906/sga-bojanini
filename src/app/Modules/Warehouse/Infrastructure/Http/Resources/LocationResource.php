<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $location = $this->resource;

        if (method_exists($location, 'getId')) {
            return [
                'id'          => $location->getId(),
                'zone_id'     => $location->getZoneId(),
                'name'        => $location->getName(),
                'code'        => $location->getCode(),
                'capacity'    => $location->getCapacity(),
                'description' => $location->getDescription(),
                'is_active'   => $location->isActive(),
            ];
        }

        return [
            'id'          => $location->id,
            'zone_id'     => $location->zone_id,
            'name'        => $location->name,
            'code'        => $location->code,
            'capacity'    => $location->capacity,
            'description' => $location->description,
            'is_active'   => $location->is_active,
            'created_at'  => $location->created_at?->toIso8601String(),
        ];
    }
}
