<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Entities;

/** Sitio dentro de una sede (ej. Consultorio 1, Call center). */
class AssetPlace
{
    public function __construct(
        private ?int $id,
        private int $siteId,
        private string $name,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSiteId(): int
    {
        return $this->siteId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
