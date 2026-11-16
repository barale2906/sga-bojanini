<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SensorResource extends JsonResource
{
    /**
     * Datos complementarios (zona + almacén, calibración vigente, último registro).
     *
     * @see \App\Modules\Monitoring\Infrastructure\Queries\SensorOverviewQuery
     */
    private ?array $overview = null;

    public function withOverview(?array $overview): static
    {
        $this->overview = $overview;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $sensor = $this->resource;

        if (method_exists($sensor, 'getId')) {
            $data = [
                'id' => $sensor->getId(),
                'zone_id' => $sensor->getZoneId(),
                'code' => $sensor->getCode(),
                'name' => $sensor->getName(),
                'type' => $sensor->getType(),
                'unit' => $sensor->getUnit(),
                'is_active' => $sensor->isActive(),
            ];
        } else {
            $data = [
                'id' => $sensor->id,
                'zone_id' => $sensor->zone_id,
                'code' => $sensor->code,
                'name' => $sensor->name,
                'type' => $sensor->type,
                'unit' => $sensor->unit,
                'is_active' => (bool) $sensor->is_active,
            ];
        }

        if ($this->overview !== null) {
            $data['zone'] = $this->overview['zone'];
            $data['calibration'] = $this->overview['calibration'];
            $data['last_reading'] = $this->overview['last_reading'];
        }

        return $data;
    }
}
