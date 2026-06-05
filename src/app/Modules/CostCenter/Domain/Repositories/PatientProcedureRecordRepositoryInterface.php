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
}
