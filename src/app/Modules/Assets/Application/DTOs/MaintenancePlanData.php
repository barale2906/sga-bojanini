<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

readonly class MaintenancePlanData
{
    public function __construct(
        public string $maintenanceType,
        public int $frequencyValue,
        public string $frequencyUnit,
        public string $startDate,
        public string $performedByType,
        public ?int $technicianId = null,
        public ?int $supplierId = null,
        public ?string $notes = null,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            maintenanceType: $data['maintenance_type'],
            frequencyValue:  (int) $data['frequency_value'],
            frequencyUnit:   $data['frequency_unit'],
            startDate:       $data['start_date'],
            performedByType: $data['performed_by_type'],
            technicianId:    isset($data['technician_id']) ? (int) $data['technician_id'] : null,
            supplierId:      isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            notes:           $data['notes'] ?? null,
            isActive:        (bool) ($data['is_active'] ?? true),
        );
    }
}
