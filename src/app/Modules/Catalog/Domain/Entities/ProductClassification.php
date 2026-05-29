<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

/**
 * Entidad de dominio que representa una clasificación de producto (ej: Medicamento, Dispositivo Médico).
 *
 * Los flags booleanos determinan qué campos aplican a los productos de este tipo,
 * permitiendo agregar nuevas clasificaciones sin modificar código.
 */
class ProductClassification
{
    public function __construct(
        private ?int $id,
        private string $code,
        private string $name,
        private ?string $description,
        private bool $hasSanitaryRegistration,
        private bool $hasConcentration,
        private bool $hasRiskLevel,
        private bool $hasPharmaFields,
        private bool $hasDeviceFields,
        private bool $hasLabBrand,
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** Indica si los productos de esta clasificación aplican registro sanitario. */
    public function hasSanitaryRegistration(): bool
    {
        return $this->hasSanitaryRegistration;
    }

    /** Indica si los productos de esta clasificación registran concentración (medicamentos). */
    public function hasConcentration(): bool
    {
        return $this->hasConcentration;
    }

    /** Indica si los productos de esta clasificación registran nivel de riesgo (dispositivos médicos). */
    public function hasRiskLevel(): bool
    {
        return $this->hasRiskLevel;
    }

    /** Indica si los productos de esta clasificación tienen campos farmacéuticos (forma farmacéutica, presentación comercial). */
    public function hasPharmaFields(): bool
    {
        return $this->hasPharmaFields;
    }

    /** Indica si los productos de esta clasificación tienen campos de dispositivo (serie/referencia, vida útil). */
    public function hasDeviceFields(): bool
    {
        return $this->hasDeviceFields;
    }

    /** Indica si los productos de esta clasificación requieren laboratorio/marca. */
    public function hasLabBrand(): bool
    {
        return $this->hasLabBrand;
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
