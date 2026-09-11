<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseWithZonesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'code'        => $this->code,
            'address'     => $this->address,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'zones'       => $this->zones->map(fn ($zone) => [
                'id'           => $zone->id,
                'warehouse_id' => $zone->warehouse_id,
                'name'         => $zone->name,
                'code'         => $zone->code,
                'type'         => $zone->type,
                'temp_min'     => $zone->temp_min !== null ? (float) $zone->temp_min : null,
                'temp_max'     => $zone->temp_max !== null ? (float) $zone->temp_max : null,
                'humidity_min' => $zone->humidity_min !== null ? (float) $zone->humidity_min : null,
                'humidity_max' => $zone->humidity_max !== null ? (float) $zone->humidity_max : null,
                'description'  => $zone->description,
                'is_active'    => $zone->is_active,
                'locations'    => $zone->locations->map(fn ($loc) => [
                    'id'            => $loc->id,
                    'zone_id'       => $loc->zone_id,
                    'name'          => $loc->name,
                    'code'          => $loc->code,
                    'volume_cm3'    => $loc->volume_cm3 !== null ? (float) $loc->volume_cm3 : null,
                    'max_weight_kg' => $loc->max_weight_kg !== null ? (float) $loc->max_weight_kg : null,
                    'description'   => $loc->description,
                    'is_active'     => $loc->is_active,
                ]),
            ]),
        ];
    }
}
