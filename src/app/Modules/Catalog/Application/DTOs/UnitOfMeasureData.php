<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

class UnitOfMeasureData
{
    public function __construct(
        public readonly string $name,
        public readonly string $abbreviation,
        public readonly bool $isBase = false,
    ) {}
}
