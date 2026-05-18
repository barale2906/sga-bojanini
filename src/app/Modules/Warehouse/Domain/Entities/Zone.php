<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Entities;

class Zone
{
    public function __construct(
        private ?int $id,
        private int $warehouseId,
        private string $name,
        private string $code,
        private string $type,
        private ?float $tempMin = null,
        private ?float $tempMax = null,
        private ?float $humidityMin = null,
        private ?float $humidityMax = null,
        private ?string $description = null,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWarehouseId(): int
    {
        return $this->warehouseId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTempMin(): ?float
    {
        return $this->tempMin;
    }

    public function getTempMax(): ?float
    {
        return $this->tempMax;
    }

    public function getHumidityMin(): ?float
    {
        return $this->humidityMin;
    }

    public function getHumidityMax(): ?float
    {
        return $this->humidityMax;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function isWithinTemperatureRange(float $temperature): bool
    {
        if ($this->tempMin === null && $this->tempMax === null) {
            return true;
        }

        if ($this->tempMin !== null && $temperature < $this->tempMin) {
            return false;
        }

        if ($this->tempMax !== null && $temperature > $this->tempMax) {
            return false;
        }

        return true;
    }

    public function isWithinHumidityRange(float $humidity): bool
    {
        if ($this->humidityMin === null && $this->humidityMax === null) {
            return true;
        }

        if ($this->humidityMin !== null && $humidity < $this->humidityMin) {
            return false;
        }

        if ($this->humidityMax !== null && $humidity > $this->humidityMax) {
            return false;
        }

        return true;
    }
}
