<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

class ProductPresentation
{
    public function __construct(
        private ?int $id,
        private ?int $parentId,
        private string $name,
        private string $code,
        private int $unitsOfMeasureId,
        private ?int $quantityPerParent,
        private int $factorToBase,
        private int $level,
        private bool $isActive = true,
        private int $sortOrder = 0,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getUnitsOfMeasureId(): int
    {
        return $this->unitsOfMeasureId;
    }

    public function getQuantityPerParent(): ?int
    {
        return $this->quantityPerParent;
    }

    public function getFactorToBase(): int
    {
        return $this->factorToBase;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
