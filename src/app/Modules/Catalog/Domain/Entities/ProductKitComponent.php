<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

class ProductKitComponent
{
    public function __construct(
        private ?int $id,
        private int $kitGenericId,
        private int $componentGenericId,
        private int $quantityPerKit,
        private int $sortOrder = 0,
        private ?string $notes = null,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKitGenericId(): int
    {
        return $this->kitGenericId;
    }

    public function getComponentGenericId(): int
    {
        return $this->componentGenericId;
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
