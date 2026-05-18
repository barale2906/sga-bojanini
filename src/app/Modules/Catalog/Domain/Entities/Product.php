<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

use App\Modules\Catalog\Domain\Enums\ProductType;

class Product
{
    public function __construct(
        private ?int $id,
        private int $categoryId,
        private int $baseUnitId,
        private ProductType $productType,
        private string $name,
        private string $code,
        private ?string $sku = null,
        private ?string $description = null,
        private bool $requiresColdChain = false,
        private int $reorderPoint = 0,
        private int $reorderQuantity = 0,
        private int $minStock = 0,
        private int $maxStock = 0,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getBaseUnitId(): int
    {
        return $this->baseUnitId;
    }

    public function getProductType(): ProductType
    {
        return $this->productType;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function requiresColdChain(): bool
    {
        return $this->requiresColdChain;
    }

    public function getReorderPoint(): int
    {
        return $this->reorderPoint;
    }

    public function getReorderQuantity(): int
    {
        return $this->reorderQuantity;
    }

    public function getMinStock(): int
    {
        return $this->minStock;
    }

    public function getMaxStock(): int
    {
        return $this->maxStock;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function needsReorder(int $currentStock): bool
    {
        return $this->reorderPoint > 0 && $currentStock <= $this->reorderPoint;
    }

    public function isKit(): bool
    {
        return $this->productType === ProductType::Kit;
    }

    public function isSimple(): bool
    {
        return $this->productType === ProductType::Simple;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }
}
