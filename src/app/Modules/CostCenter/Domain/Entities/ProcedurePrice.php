<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Entities;

use DateTimeImmutable;

/**
 * Tarifa/precio de referencia para un procedimiento médico.
 *
 * Permite manejar histórico de precios mediante effective_from / effective_to.
 * Solo aplica a MedicalService de tipo PROCEDURE.
 */
class ProcedurePrice
{
    public function __construct(
        private ?int $id,
        private int $medicalServiceId,
        private float $unitPrice,
        private DateTimeImmutable $effectiveFrom,
        private ?DateTimeImmutable $effectiveTo,
        private bool $isActive = true,
        private ?string $notes = null,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMedicalServiceId(): int
    {
        return $this->medicalServiceId;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getEffectiveFrom(): DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function getEffectiveTo(): ?DateTimeImmutable
    {
        return $this->effectiveTo;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->isActive) {
            return false;
        }

        $now = new DateTimeImmutable('today');

        if ($now < $this->effectiveFrom) {
            return false;
        }

        return $this->effectiveTo === null || $now <= $this->effectiveTo;
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
