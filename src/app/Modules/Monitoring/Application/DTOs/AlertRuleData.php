<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\DTOs;

class AlertRuleData
{
    public function __construct(
        public readonly int $sensorId,
        public readonly string $conditionType,
        public readonly ?float $threshold,
        public readonly int $consecutiveReadings,
        public readonly array $notificationChannels,
        public readonly bool $isActive = true,
    ) {}
}
