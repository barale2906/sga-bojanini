<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $zone = $this->resource;

        if (method_exists($zone, 'getId')) {
            return [
                'id'            => $zone->getId(),
                'warehouse_id'  => $zone->getWarehouseId(),
                'name'          => $zone->getName(),
                'code'          => $zone->getCode(),
                'type'          => $zone->getType(),
                'temp_min'      => $zone->getTempMin(),
                'temp_max'      => $zone->getTempMax(),
                'humidity_min'  => $zone->getHumidityMin(),
                'humidity_max'  => $zone->getHumidityMax(),
                'description'   => $zone->getDescription(),
                'is_active'     => $zone->isActive(),
            ];
        }

        return [
            'id'            => $zone->id,
            'warehouse_id'  => $zone->warehouse_id,
            'name'          => $zone->name,
            'code'          => $zone->code,
            'type'          => $zone->type,
            'temp_min'      => $zone->temp_min,
            'temp_max'      => $zone->temp_max,
            'humidity_min'  => $zone->humidity_min,
            'humidity_max'  => $zone->humidity_max,
            'description'   => $zone->description,
            'is_active'     => $zone->is_active,
            'created_at'    => $zone->created_at?->toIso8601String(),
        ];
    }
}
