<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Entities;

use App\Modules\Assets\Domain\Enums\AssetType;

/**
 * Categoría de activos. Su tipo define si los activos llevan hoja de vida
 * y gestión de mantenimiento.
 */
class AssetCategory
{
    public function __construct(
        private ?int $id,
        private string $code,
        private string $name,
        private AssetType $assetType,
        private ?string $description,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAssetType(): AssetType
    {
        return $this->assetType;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
