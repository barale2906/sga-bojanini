<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

class ProductKitComponent
{
    public function __construct(
        private ?int $id,
        private int $kitProductId,
        private int $componentProductId,
        private int $quantityPerKit,
        private int $sortOrder = 0,
        private ?string $notes = null,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKitProductId(): int
    {
        return $this->kitProductId;
    }

    public function getComponentProductId(): int
    {
        return $this->componentProductId;
    }

    public function getQuantityPerKit(): int
    {
        return $this->quantityPerKit;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
