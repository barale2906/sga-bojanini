<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Entities;

class StockMovement
{
    public function __construct(
        private ?int $id,
        private int $warehouseId,
        private int $productId,
        private ?int $batchId,
        private ?int $locationFromId,
        private ?int $locationToId,
        private string $movementType,
        private int $quantity,
        private ?string $reason = null,
        private ?string $referenceType = null,
        private ?int $referenceId = null,
        private int $userId = 0,
        private ?string $createdAt = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouseId;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getBatchId(): ?int
    {
        return $this->batchId;
    }

    public function getLocationFromId(): ?int
    {
        return $this->locationFromId;
    }

    public function getLocationToId(): ?int
    {
        return $this->locationToId;
    }

    public function getMovementType(): string
    {
        return $this->movementType;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}
