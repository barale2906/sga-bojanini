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
        private ?string $seller = null,
        private ?string $referrer = null,
        private ?int $movementDocumentId = null,
        private ?string $orderNumber = null,
        private ?string $discountType = null,
        private ?float $discountValue = null,
        private ?float $discountAmount = null,
        private ?float $netTotal = null,
        private ?string $discountStatus = null,
        private ?int $createdByUserId = null,
        private ?int $approvedByUserId = null,
        private ?DateTimeImmutable $approvedAt = null,
        private ?string $patientEmail = null,
        private ?string $patientAddress = null,
        private ?string $patientPhone = null,
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
        ?string $seller = null,
        ?string $referrer = null,
        ?int $movementDocumentId = null,
        ?string $orderNumber = null,
        ?string $discountType = null,
        ?float $discountValue = null,
        ?float $discountAmount = null,
        ?float $netTotal = null,
        ?string $discountStatus = null,
        ?int $createdByUserId = null,
        ?string $patientEmail = null,
        ?string $patientAddress = null,
        ?string $patientPhone = null,
    ): self {
        $total = self::calculateTotal($quantity, $unitPrice);

        return new self(
            id:                 null,
            medicalServiceId:   $medicalServiceId,
            patientExternalId:  $patientExternalId,
            patientDocument:    $patientDocument,
            patientFirstName:   $patientFirstName,
            patientLastName:    $patientLastName,
            quantity:           $quantity,
            unitPrice:          $unitPrice,
            total:              $total,
            serviceDate:        $serviceDate,
            notes:              $notes,
            seller:             $seller,
            referrer:           $referrer,
            movementDocumentId: $movementDocumentId,
            orderNumber:        $orderNumber,
            discountType:       $discountType,
            discountValue:      $discountValue,
            discountAmount:     $discountAmount,
            netTotal:           $netTotal ?? ($discountAmount !== null ? round($total - $discountAmount, 2) : null),
            discountStatus:     $discountStatus,
            createdByUserId:    $createdByUserId,
            patientEmail:       $patientEmail,
            patientAddress:     $patientAddress,
            patientPhone:       $patientPhone,
        );
    }

    public static function calculateDiscountAmount(float $total, ?string $type, ?float $value): float
    {
        if ($type === null || $value === null) {
            return 0.0;
        }

        return match ($type) {
            'fixed'      => round($value, 2),
            'percentage' => round($total * $value / 100, 2),
            default      => 0.0,
        };
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

    public function getSeller(): ?string
    {
        return $this->seller;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function getMovementDocumentId(): ?int
    {
        return $this->movementDocumentId;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function getDiscountType(): ?string
    {
        return $this->discountType;
    }

    public function getDiscountValue(): ?float
    {
        return $this->discountValue;
    }

    public function getDiscountAmount(): ?float
    {
        return $this->discountAmount;
    }

    public function getNetTotal(): ?float
    {
        return $this->netTotal;
    }

    public function getDiscountStatus(): ?string
    {
        return $this->discountStatus;
    }

    public function getCreatedByUserId(): ?int
    {
        return $this->createdByUserId;
    }

    public function getApprovedByUserId(): ?int
    {
        return $this->approvedByUserId;
    }

    public function getApprovedAt(): ?DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getPatientEmail(): ?string
    {
        return $this->patientEmail;
    }

    public function getPatientAddress(): ?string
    {
        return $this->patientAddress;
    }

    public function getPatientPhone(): ?string
    {
        return $this->patientPhone;
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
