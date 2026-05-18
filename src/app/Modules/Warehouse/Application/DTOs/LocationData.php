<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\DTOs;

class LocationData
{
    public function __construct(
        public readonly int $zoneId,
        public readonly string $name,
        public readonly string $code,
        public readonly ?int $capacity = null,
        public readonly ?string $description = null,
    ) {}
}
