<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Resources;

use App\Modules\Monitoring\Domain\Entities\SensorReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SensorReading */
class ReadingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->getId(),
            'sensor_id'      => $this->getSensorId(),
            'value'          => $this->getValue(),
            'reading_source' => $this->getReadingSource(),
            'recorded_at'    => $this->getRecordedAt()->format('Y-m-d H:i:s'),
            'user_id'        => $this->getUserId(),
        ];
    }
}
