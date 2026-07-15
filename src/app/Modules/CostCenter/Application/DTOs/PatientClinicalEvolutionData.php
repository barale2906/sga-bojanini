<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\DTOs;

use DateTimeImmutable;

class PatientClinicalEvolutionData
{
    public function __construct(
        public readonly int $patientProcedureRecordId,
        public readonly string $content,
        public readonly int $userId,
        public readonly DateTimeImmutable $recordedAt,
    ) {}
}
