<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Entities;

use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;

/**
 * Activo de la institución (equipo biomédico, de cómputo o bien general).
 *
 * La ubicación (`currentPlaceId`, `isOffPremises`) y el responsable solo
 * cambian mediante traslados; el estado `retired` solo mediante una baja.
 */
class Asset
{
    public function __construct(
        private ?int $id,
        private string $code,
        private string $name,
        private int $categoryId,
        private AssetType $assetType,
        private AssetStatus $status,
        private ?int $currentPlaceId,
        private bool $isOffPremises = false,
        private ?string $offPremisesLocation = null,
        private ?int $responsibleId = null,
        private ?string $description = null,
        private ?string $brand = null,
        private ?string $model = null,
        private ?string $serial = null,
        private ?string $acquisitionDate = null,
        private ?string $acquisitionValue = null,
        private ?int $supplierId = null,
        private ?string $invoiceNumber = null,
        private ?string $warrantyUntil = null,
        private ?string $notes = null,
        private ?string $retiredAt = null,
        private ?string $retirementReason = null,
    ) {}

    // ── Reglas de negocio ─────────────────────────────────────────────────

    public function isRetired(): bool
    {
        return $this->status === AssetStatus::Retired;
    }

    /** @throws \DomainException si el activo está de baja */
    public function ensureNotRetired(): void
    {
        if ($this->isRetired()) {
            throw new \DomainException("El activo {$this->code} está dado de baja y no admite cambios.");
        }
    }

    /** @throws \DomainException si el activo no lleva mantenimiento o está de baja */
    public function ensureSupportsMaintenance(): void
    {
        $this->ensureNotRetired();

        if (! $this->assetType->requiresMaintenance()) {
            throw new \DomainException(
                "El activo {$this->code} es de tipo general: no lleva hoja de vida ni mantenimientos."
            );
        }
    }

    /** @throws \DomainException */
    public function changeStatus(AssetStatus $status): void
    {
        $this->ensureNotRetired();

        if (! in_array($status, AssetStatus::manuallySettable(), true)) {
            throw new \DomainException('La baja de un activo se registra como traslado de tipo baja, con firma.');
        }

        if ($status === $this->status) {
            throw new \DomainException("El activo ya se encuentra en estado «{$status->label()}».");
        }

        $this->status = $status;
    }

    // ── Getters ───────────────────────────────────────────────────────────

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

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getAssetType(): AssetType
    {
        return $this->assetType;
    }

    public function getStatus(): AssetStatus
    {
        return $this->status;
    }

    public function getCurrentPlaceId(): ?int
    {
        return $this->currentPlaceId;
    }

    public function isOffPremises(): bool
    {
        return $this->isOffPremises;
    }

    public function getOffPremisesLocation(): ?string
    {
        return $this->offPremisesLocation;
    }

    public function getResponsibleId(): ?int
    {
        return $this->responsibleId;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getSerial(): ?string
    {
        return $this->serial;
    }

    public function getAcquisitionDate(): ?string
    {
        return $this->acquisitionDate;
    }

    public function getAcquisitionValue(): ?string
    {
        return $this->acquisitionValue;
    }

    public function getSupplierId(): ?int
    {
        return $this->supplierId;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function getWarrantyUntil(): ?string
    {
        return $this->warrantyUntil;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getRetiredAt(): ?string
    {
        return $this->retiredAt;
    }

    public function getRetirementReason(): ?string
    {
        return $this->retirementReason;
    }
}
