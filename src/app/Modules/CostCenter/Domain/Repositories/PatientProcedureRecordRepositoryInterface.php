<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Repositories;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;

interface PatientProcedureRecordRepositoryInterface
{
    public function findById(int $id): ?PatientProcedureRecord;

    /** @return PatientProcedureRecord[] */
    public function findAll(array $filters = []): array;

    /**
     * Registros de un paciente con nombre del procedimiento y del servicio padre.
     * Retorna arrays enriquecidos, no entidades, porque agrega datos de relaciones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByPatientWithService(string $patientExternalId, array $filters = []): array;

    public function save(PatientProcedureRecord $record): PatientProcedureRecord;

    public function delete(int $id): void;

    /** @return PatientProcedureRecord[] */
    public function createBatch(array $records): array;

    /** @return PatientProcedureRecord[] */
    public function findByOrderNumber(string $orderNumber): array;

    /**
     * Órdenes con algún registro en discount_status='pending'.
     * @return array<int, array<string, mixed>>
     */
    public function findPendingDiscountOrders(array $filters = []): array;

    public function approveDiscountsForOrder(string $orderNumber, int $userId, \DateTimeImmutable $at): void;

    /**
     * Cambia el billing_status de todos los registros de una orden.
     * Status válidos: 'billed' | 'cancelled'
     */
    public function updateBillingStatusForOrder(string $orderNumber, string $status, int $userId, \DateTimeImmutable $at): void;
}
