<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\PatientProcedureRecordData;
use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class UpdatePatientProcedureRecordUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repository,
    ) {}

    public function execute(int $id, PatientProcedureRecordData $data): PatientProcedureRecord
    {
        $current = $this->repository->findById($id);

        if ($current === null) {
            throw new \DomainException("Registro de procedimiento con id {$id} no encontrado.");
        }

        if ($data->quantity <= 0) {
            throw new \DomainException('La cantidad debe ser mayor a cero.');
        }

        if ($data->unitPrice < 0) {
            throw new \DomainException('El precio unitario no puede ser negativo.');
        }

        // Los campos opcionales que el cliente no envió se conservan; lo demás
        // (orden, descuento, creador, aprobación, contacto del paciente y
        // facturación) no lo gestiona esta operación y nunca se pierde.
        $sent = fn (string $field): bool => $data->providedFields === null
            || in_array($field, $data->providedFields, true);

        $total          = PatientProcedureRecord::calculateTotal($data->quantity, $data->unitPrice);
        $discountAmount = PatientProcedureRecord::calculateDiscountAmount(
            $total,
            $current->getDiscountType(),
            $current->getDiscountValue(),
        );

        $updated = new PatientProcedureRecord(
            id:                 $id,
            medicalServiceId:   $data->medicalServiceId,
            patientExternalId:  $data->patientExternalId,
            patientDocument:    $data->patientDocument,
            patientFirstName:   $data->patientFirstName,
            patientLastName:    $data->patientLastName,
            quantity:           $data->quantity,
            unitPrice:          $data->unitPrice,
            total:              $total,
            serviceDate:        $data->serviceDate,
            notes:              $sent('notes') ? $data->notes : $current->getNotes(),
            isActive:           $sent('is_active') ? $data->isActive : $current->isActive(),
            seller:             $sent('seller') ? $data->seller : $current->getSeller(),
            referrer:           $sent('referrer') ? $data->referrer : $current->getReferrer(),
            movementDocumentId: $sent('movement_document_id') ? $data->movementDocumentId : $current->getMovementDocumentId(),
            orderNumber:        $current->getOrderNumber(),
            discountType:       $current->getDiscountType(),
            discountValue:      $current->getDiscountValue(),
            discountAmount:     $discountAmount > 0 ? $discountAmount : null,
            netTotal:           $discountAmount > 0 ? round($total - $discountAmount, 2) : null,
            discountStatus:     $current->getDiscountStatus(),
            createdByUserId:    $current->getCreatedByUserId(),
            approvedByUserId:   $current->getApprovedByUserId(),
            approvedAt:         $current->getApprovedAt(),
            patientEmail:       $current->getPatientEmail(),
            patientAddress:     $current->getPatientAddress(),
            patientPhone:       $current->getPatientPhone(),
            billingStatus:      $current->getBillingStatus(),
            billedByUserId:     $current->getBilledByUserId(),
            billedAt:           $current->getBilledAt(),
        );

        return $this->repository->save($updated);
    }
}
