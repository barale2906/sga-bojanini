<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\ValueObjects;

class ProductStockSummary
{
    public function __construct(
        private readonly string $productCode,
        private readonly int $currentQuantity,
    ) {}

    public function getProductCode(): string
    {
        return $this->productCode;
    }

    public function getCurrentQuantity(): int
    {
        return $this->currentQuantity;
    }
}
