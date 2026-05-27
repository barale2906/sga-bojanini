<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Domain\Repositories\CapacityRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class GetZoneCapacityUseCase
{
    public function __construct(
        private readonly ZoneRepositoryInterface $zoneRepository,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly CapacityRepositoryInterface $capacityRepository,
    ) {}

    /**
     * Retorna las métricas de capacidad de una zona con el detalle por ubicación.
     */
    public function execute(int $zoneId): array
    {
        $zone = $this->zoneRepository->findById($zoneId);

        if ($zone === null) {
            throw new \DomainException('Zona no encontrada.');
        }

        // Uso agregado de toda la zona
        $zoneUsage = $this->capacityRepository->getZoneUsage($zoneId);

        // Totales de capacidad máxima sumando las ubicaciones con datos definidos
        $locations    = $this->locationRepository->findByZoneId($zoneId);
        $totalMaxVol  = null;
        $totalMaxWgt  = null;

        $locationMetrics = [];

        foreach ($locations as $loc) {
            // Acumular máximos solo cuando la ubicación tiene definido el campo
            if ($loc->getVolumeCm3() !== null) {
                $totalMaxVol = ($totalMaxVol ?? 0.0) + $loc->getVolumeCm3();
            }

            if ($loc->getMaxWeightKg() !== null) {
                $totalMaxWgt = ($totalMaxWgt ?? 0.0) + $loc->getMaxWeightKg();
            }

            $locUsage          = $this->capacityRepository->getLocationUsage((int) $loc->getId());
            $locationMetrics[] = [
                'id'      => $loc->getId(),
                'name'    => $loc->getName(),
                'code'    => $loc->getCode(),
                'capacity_volume' => $this->buildVolumeMetrics($loc->getVolumeCm3(), $locUsage['used_volume_cm3']),
                'capacity_weight' => $this->buildWeightMetrics($loc->getMaxWeightKg(), $locUsage['used_weight_kg']),
            ];
        }

        return [
            'id'             => $zone->getId(),
            'name'           => $zone->getName(),
            'code'           => $zone->getCode(),
            'warehouse_id'   => $zone->getWarehouseId(),
            'capacity_volume' => $this->buildVolumeMetrics($totalMaxVol, $zoneUsage['used_volume_cm3']),
            'capacity_weight' => $this->buildWeightMetrics($totalMaxWgt, $zoneUsage['used_weight_kg']),
            'locations'       => $locationMetrics,
        ];
    }

    private function buildVolumeMetrics(?float $maxCm3, float $usedCm3): array
    {
        $availableCm3 = $maxCm3 !== null ? max(0.0, $maxCm3 - $usedCm3) : null;
        $usagePct     = ($maxCm3 !== null && $maxCm3 > 0) ? round(($usedCm3 / $maxCm3) * 100, 2) : null;

        return [
            'max_cm3'       => $maxCm3,
            'used_cm3'      => round($usedCm3, 2),
            'available_cm3' => $availableCm3 !== null ? round($availableCm3, 2) : null,
            'usage_pct'     => $usagePct,
        ];
    }

    private function buildWeightMetrics(?float $maxKg, float $usedKg): array
    {
        $availableKg = $maxKg !== null ? max(0.0, $maxKg - $usedKg) : null;
        $usagePct    = ($maxKg !== null && $maxKg > 0) ? round(($usedKg / $maxKg) * 100, 2) : null;

        return [
            'max_kg'       => $maxKg,
            'used_kg'      => round($usedKg, 2),
            'available_kg' => $availableKg !== null ? round($availableKg, 2) : null,
            'usage_pct'    => $usagePct,
        ];
    }
}
