<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Entities;

use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use Carbon\Carbon;

class Batch
{
    public function __construct(
        private ?int $id,
        private int $productVariantId,
        private string $lotNumber,
        private string $expirationDate,
        private ?string $manufacturingDate,
        private int $quantityReceived,
        private int $quantityAvailable,
        private string $status = 'active',
        private ?string $notes = null,
        private ?string $receivedAt = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductVariantId(): int
    {
        return $this->productVariantId;
    }

    public function getLotNumber(): string
    {
        return $this->lotNumber;
    }

    public function getExpirationDate(): string
    {
        return $this->expirationDate;
    }

    public function getManufacturingDate(): ?string
    {
        return $this->manufacturingDate;
    }

    public function getQuantityReceived(): int
    {
        return $this->quantityReceived;
    }

    public function getQuantityAvailable(): int
    {
        return $this->quantityAvailable;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getReceivedAt(): ?string
    {
        return $this->receivedAt;
    }

    public function isExpired(): bool
    {
        return Carbon::parse($this->expirationDate)->isPast();
    }

    public function isExpiringSoon(int $days): bool
    {
        $expirationDate = Carbon::parse($this->expirationDate);
        $alertDate = now()->addDays($days);

        return $expirationDate->isBetween(now(), $alertDate);
    }

    public function daysUntilExpiry(): int
    {
        return (int) now()->diffInDays(Carbon::parse($this->expirationDate), false);
    }

    public function deductQuantity(int $qty): void
    {
        if ($qty > $this->quantityAvailable) {
            throw new InsufficientStockException(
                "El lote {$this->lotNumber} solo tiene {$this->quantityAvailable} unidades disponibles, se pidieron {$qty}."
            );
        }

        $this->quantityAvailable -= $qty;

        if ($this->quantityAvailable === 0) {
            $this->status = 'depleted';
        }
    }
}
