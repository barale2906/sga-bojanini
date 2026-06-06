<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Entities;

use DateTimeImmutable;

/**
 * Registro de un procedimiento médico realizado a un paciente.
 *
 * Captura la cantidad ejecutada, el precio unitario real cobrado y el total.
 * El total siempre es calculado como quantity * unitPrice y nunca se guarda en estado inconsistente.
 */
class PatientProcedureRecord
{
    public function __construct(
        private ?int $id,
        private int $medicalServiceId,
        private string $patientExternalId,
        private string $patientDocument,
        private string $patientFirstName,
        private string $patientLastName,
        private float $quantity,
        private float $unitPrice,
        private float $total,
        private DateTimeImmutable $serviceDate,
        private ?string $notes = null,
        private bool $isActive = true,
        private ?string $medicalServiceName = null,
    ) {}

    public static function create(
        int $medicalServiceId,
        string $patientExternalId,
        string $patientDocument,
        string $patientFirstName,
        string $patientLastName,
        float $quantity,
        float $unitPrice,
        DateTimeImmutable $serviceDate,
        ?string $notes = null,
    ): self {
        return new self(
            id:               null,
            medicalServiceId: $medicalServiceId,
            patientExternalId: $patientExternalId,
            patientDocument:  $patientDocument,
            patientFirstName: $patientFirstName,
            patientLastName:  $patientLastName,
            quantity:         $quantity,
            unitPrice:        $unitPrice,
            total:            self::calculateTotal($quantity, $unitPrice),
            serviceDate:      $serviceDate,
            notes:            $notes,
        );
    }

    public static function calculateTotal(float $quantity, float $unitPrice): float
    {
        return round($quantity * $unitPrice, 2);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMedicalServiceId(): int
    {
        return $this->medicalServiceId;
    }

    public function getPatientExternalId(): string
    {
        return $this->patientExternalId;
    }

    public function getPatientDocument(): string
    {
        return $this->patientDocument;
    }

    public function getPatientFirstName(): string
    {
        return $this->patientFirstName;
    }

    public function getPatientLastName(): string
    {
        return $this->patientLastName;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function getServiceDate(): DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getMedicalServiceName(): ?string
    {
        return $this->medicalServiceName;
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
