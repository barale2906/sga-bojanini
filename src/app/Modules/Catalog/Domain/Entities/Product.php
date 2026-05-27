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
        private ?float $volumeCm3 = null,
        private ?float $weightKg = null,
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

    public function getVolumeCm3(): ?float
    {
        return $this->volumeCm3;
    }

    public function getWeightKg(): ?float
    {
        return $this->weightKg;
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

    /** Calcula el volumen total que ocupa una cantidad dada de este producto (cm³). */
    public function totalVolume(int $quantity): ?float
    {
        if ($this->volumeCm3 === null) {
            return null;
        }

        return $this->volumeCm3 * $quantity;
    }

    /** Calcula el peso total que aporta una cantidad dada de este producto (kg). */
    public function totalWeight(int $quantity): ?float
    {
        if ($this->weightKg === null) {
            return null;
        }

        return $this->weightKg * $quantity;
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
