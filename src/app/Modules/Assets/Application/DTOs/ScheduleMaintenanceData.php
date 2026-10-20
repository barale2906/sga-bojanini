<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

/** Orden de mantenimiento puntual (correctivo o programada a mano). */
readonly class ScheduleMaintenanceData
{
    public function __construct(
        public string $maintenanceType,
        public string $scheduledDate,
        public ?string $reportedFailure = null,
        public ?string $performedByType = null,
        public ?int $technicianId = null,
        public ?int $supplierId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            maintenanceType: $data['maintenance_type'],
            scheduledDate:   $data['scheduled_date'],
            reportedFailure: $data['reported_failure'] ?? null,
            performedByType: $data['performed_by_type'] ?? null,
            technicianId:    isset($data['technician_id']) ? (int) $data['technician_id'] : null,
            supplierId:      isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
        );
    }
}
