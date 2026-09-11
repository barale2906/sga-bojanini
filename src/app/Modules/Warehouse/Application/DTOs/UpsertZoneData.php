<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\DTOs;

class UpsertZoneData
{
    /**
     * @param UpsertLocationData[] $locations
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly string $type,
        public readonly ?float $tempMin = null,
        public readonly ?float $tempMax = null,
        public readonly ?float $humidityMin = null,
        public readonly ?float $humidityMax = null,
        public readonly ?string $description = null,
        public readonly array $locations = [],
    ) {}
}
