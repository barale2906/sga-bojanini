<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

/**
 * Registro de la ejecución de un mantenimiento. Es obligatorio dejar
 * constancia de qué se hizo, cuándo, el resultado y quién lo ejecutó;
 * los soportes adjuntos son opcionales.
 */
readonly class CompleteMaintenanceData
{
    /**
     * @param array<int, array{name: string, quantity: int|float, cost?: float|null}> $partsReplaced
     */
    public function __construct(
        public string $performedAt,
        public string $performedByType,
        public string $activitiesPerformed,
        public string $result,
        public ?int $technicianId = null,
        public ?int $supplierId = null,
        public ?string $externalTechnicianName = null,
        public ?string $findings = null,
        public ?string $recommendations = null,
        public array $partsReplaced = [],
        public ?string $cost = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            performedAt:            $data['performed_at'],
            performedByType:        $data['performed_by_type'],
            activitiesPerformed:    $data['activities_performed'],
            result:                 $data['result'],
            technicianId:           isset($data['technician_id']) ? (int) $data['technician_id'] : null,
            supplierId:             isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            externalTechnicianName: $data['external_technician_name'] ?? null,
            findings:               $data['findings'] ?? null,
            recommendations:        $data['recommendations'] ?? null,
            partsReplaced:          $data['parts_replaced'] ?? [],
            cost:                   isset($data['cost']) ? (string) $data['cost'] : null,
        );
    }
}
