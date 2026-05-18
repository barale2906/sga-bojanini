<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\DTOs;

class SensorData
{
    public function __construct(
        public readonly int $zoneId,
        public readonly string $code,
        public readonly string $name,
        public readonly string $type,
        public readonly string $unit,
    ) {}
}
