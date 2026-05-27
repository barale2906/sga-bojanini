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
        private ?float $volumeCm3 = null,
        private ?float $maxWeightKg = null,
        private ?string $description = null,
        private bool $isActive = true,
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

    public function getVolumeCm3(): ?float
    {
        return $this->volumeCm3;
    }

    public function getMaxWeightKg(): ?float
    {
        return $this->maxWeightKg;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** Verifica si hay volumen disponible para alojar el volumen indicado (cm³). */
    public function hasVolumeFor(float $volumeCm3, float $usedVolumeCm3 = 0.0): bool
    {
        if ($this->volumeCm3 === null) {
            return true;
        }

        return ($usedVolumeCm3 + $volumeCm3) <= $this->volumeCm3;
    }

    /** Verifica si la ubicación soporta el peso adicional indicado (kg). */
    public function hasWeightCapacityFor(float $weightKg, float $usedWeightKg = 0.0): bool
    {
        if ($this->maxWeightKg === null) {
            return true;
        }

        return ($usedWeightKg + $weightKg) <= $this->maxWeightKg;
    }
}
