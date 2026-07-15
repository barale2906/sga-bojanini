<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

use DateTimeImmutable;

/**
 * Entidad de dominio que representa un registro sanitario de un producto.
 *
 * Un producto puede tener múltiples registros sanitarios vigentes,
 * cada uno con su propio número y fecha de vencimiento.
 */
class ProductSanitaryRegistration
{
    public function __construct(
        private ?int $id,
        private int $productVariantId,
        private string $registrationNumber,
        private DateTimeImmutable $expiryDate,
        private bool $isActive = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductVariantId(): int
    {
        return $this->productVariantId;
    }

    public function getRegistrationNumber(): string
    {
        return $this->registrationNumber;
    }

    public function getExpiryDate(): DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** Determina si el registro sanitario ha vencido comparando con la fecha actual. */
    public function isExpired(): bool
    {
        return $this->expiryDate < new DateTimeImmutable('today');
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
