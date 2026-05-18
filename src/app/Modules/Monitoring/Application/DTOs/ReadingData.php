<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\DTOs;

class ReadingData
{
    public function __construct(
        public readonly int $sensorId,
        public readonly float $value,
        public readonly string $readingSource,
        public readonly string $recordedAt,
    ) {}
}
