<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\DTOs;

use DateTimeImmutable;

/**
 * DTO para registrar un procedimiento realizado a un paciente.
 *
 * @property-read int               $medicalServiceId  ID del procedimiento (type=procedure)
 * @property-read string            $patientExternalId Identificador del paciente en sistema externo
 * @property-read string            $patientDocument   Documento de identidad del paciente
 * @property-read string            $patientFirstName  Nombres del paciente
 * @property-read string            $patientLastName   Apellidos del paciente
 * @property-read float             $quantity          Cantidad de veces ejecutado el procedimiento
 * @property-read float             $unitPrice         Precio unitario cobrado
 * @property-read DateTimeImmutable $serviceDate       Fecha de la atención
 * @property-read ?string           $notes             Notas opcionales
 * @property-read bool              $isActive          Estado del registro
 */
readonly class PatientProcedureRecordData
{
    public function __construct(
        public int $medicalServiceId,
        public string $patientExternalId,
        public string $patientDocument,
        public string $patientFirstName,
        public string $patientLastName,
        public float $quantity,
        public float $unitPrice,
        public DateTimeImmutable $serviceDate,
        public ?string $notes = null,
        public bool $isActive = true,
        public ?string $seller = null,
        public ?string $referrer = null,
    ) {}
}
