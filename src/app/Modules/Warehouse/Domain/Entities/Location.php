<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Entities;

class Location
{
    public function __construct(
        private ?int $id,
        private int $zoneId,
        private string $name,
        private string $code,
        private ?int $capacity = null,
        private ?string $description = null,
        private bool $isActive = true,
        private int $currentOccupancy = 0,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZoneId(): int
    {
        return $this->zoneId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCurrentOccupancy(): int
    {
        return $this->currentOccupancy;
    }

    public function hasCapacity(int $quantity = 1): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return ($this->currentOccupancy + $quantity) <= $this->capacity;
    }

    public function getAvailableCapacity(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->currentOccupancy);
    }
}
