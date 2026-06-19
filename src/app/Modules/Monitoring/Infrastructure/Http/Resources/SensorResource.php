<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SensorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sensor = $this->resource;

        if (method_exists($sensor, 'getId')) {
            return [
                'id' => $sensor->getId(),
                'zone_id' => $sensor->getZoneId(),
                'code' => $sensor->getCode(),
                'name' => $sensor->getName(),
                'type' => $sensor->getType(),
                'unit' => $sensor->getUnit(),
                'is_active' => $sensor->isActive(),
            ];
        }

        return [
            'id' => $sensor->id,
            'zone_id' => $sensor->zone_id,
            'code' => $sensor->code,
            'name' => $sensor->name,
            'type' => $sensor->type,
            'unit' => $sensor->unit,
            'is_active' => (bool) $sensor->is_active,
        ];
    }
}
