<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\DTOs;

class UpsertLocationData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $code,
        public readonly ?float $volumeCm3 = null,
        public readonly ?float $maxWeightKg = null,
        public readonly ?string $description = null,
    ) {}
}
