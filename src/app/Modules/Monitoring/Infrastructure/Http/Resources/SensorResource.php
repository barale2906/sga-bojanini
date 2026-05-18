<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Resources;

use App\Modules\Monitoring\Domain\Entities\Sensor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sensor */
class SensorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->getId(),
            'zone_id'   => $this->getZoneId(),
            'code'      => $this->getCode(),
            'name'      => $this->getName(),
            'type'      => $this->getType(),
            'unit'      => $this->getUnit(),
            'is_active' => $this->isActive(),
        ];
    }
}
