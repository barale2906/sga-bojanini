<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Resources;

use App\Modules\Monitoring\Domain\Entities\AlertRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AlertRule */
class AlertRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->getId(),
            'sensor_id'             => $this->getSensorId(),
            'condition_type'        => $this->getConditionType(),
            'threshold'             => $this->getThreshold(),
            'consecutive_readings'  => $this->getConsecutiveReadings(),
            'notification_channels' => $this->getNotificationChannels(),
            'is_active'             => $this->isActive(),
        ];
    }
}
