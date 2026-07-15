<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use App\Modules\Catalog\Domain\Enums\ProductType;

class GenericProductData
{
    public function __construct(
        public readonly int $categoryId,
        public readonly int $baseUnitId,
        public readonly ProductType $productType,
        public readonly string $name,
        public readonly ?int $classificationId = null,
        public readonly ?string $description = null,
        public readonly ?string $concentration = null,
        public readonly ?string $pharmaceuticalForm = null,
        public readonly ?float $volumeCm3 = null,
        public readonly ?float $weightKg = null,
        public readonly bool $requiresColdChain = false,
        public readonly int $reorderPoint = 0,
        public readonly int $reorderQuantity = 0,
        public readonly int $minStock = 0,
        public readonly int $maxStock = 0,
    ) {}
}
