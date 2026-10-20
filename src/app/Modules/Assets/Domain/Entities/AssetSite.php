<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Entities;

/** Sede o edificio donde se ubican los activos (ej. Clínica, Edificio 2). */
class AssetSite
{
    public function __construct(
        private ?int $id,
        private string $name,
        private ?string $address,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
