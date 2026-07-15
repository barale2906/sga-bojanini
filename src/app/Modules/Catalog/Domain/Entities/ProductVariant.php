<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

class ProductVariant
{
    public function __construct(
        private ?int $id,
        private int $genericProductId,
        private ?string $labBrand = null,
        private ?string $brandSku = null,
        private ?string $commercialPresentation = null,
        private ?string $serieReference = null,
        private ?string $usefulLife = null,
        private ?string $riskLevel = null,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGenericProductId(): int
    {
        return $this->genericProductId;
    }

    public function getLabBrand(): ?string
    {
        return $this->labBrand;
    }

    public function getBrandSku(): ?string
    {
        return $this->brandSku;
    }

    public function getCommercialPresentation(): ?string
    {
        return $this->commercialPresentation;
    }

    public function getSerieReference(): ?string
    {
        return $this->serieReference;
    }

    public function getUsefulLife(): ?string
    {
        return $this->usefulLife;
    }

    public function getRiskLevel(): ?string
    {
        return $this->riskLevel;
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
