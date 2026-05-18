<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Entities;

class Sensor
{
    public function __construct(
        private ?int $id,
        private int $zoneId,
        private string $code,
        private string $name,
        private string $type,
        private string $unit,
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function isActive(): bool
    {
        return $this->isActive;
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
