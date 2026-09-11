<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\DTOs;

class WarehouseStructureData
{
    /**
     * @param UpsertZoneData[] $zones
     */
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $address = null,
        public readonly ?string $description = null,
        public readonly array $zones = [],
    ) {}
}
