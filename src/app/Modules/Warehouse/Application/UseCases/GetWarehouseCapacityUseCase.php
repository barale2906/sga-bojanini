<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Domain\Repositories\CapacityRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;

class GetWarehouseCapacityUseCase
{
    public function __construct(
        private readonly WarehouseRepositoryInterface $warehouseRepository,
        private readonly ZoneRepositoryInterface $zoneRepository,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly CapacityRepositoryInterface $capacityRepository,
    ) {}

    /**
     * Retorna las métricas de capacidad de un almacén con el detalle por zona y ubicación.
     */
    public function execute(int $warehouseId): array
    {
        $warehouse = $this->warehouseRepository->findById($warehouseId);

        if ($warehouse === null) {
            throw new \DomainException('Almacén no encontrado.');
        }

        $warehouseUsage = $this->capacityRepository->getWarehouseUsage($warehouseId);

        $zones        = $this->zoneRepository->findByWarehouseId($warehouseId);
        $totalMaxVol  = null;
        $totalMaxWgt  = null;
        $zoneMetrics  = [];

        foreach ($zones as $zone) {
            $zoneUsage = $this->capacityRepository->getZoneUsage((int) $zone->getId());

            $locations   = $this->locationRepository->findByZoneId((int) $zone->getId());
            $zoneMaxVol  = null;
            $zoneMaxWgt  = null;
            $locMetrics  = [];

            foreach ($locations as $loc) {
                if ($loc->getVolumeCm3() !== null) {
                    $zoneMaxVol = ($zoneMaxVol ?? 0.0) + $loc->getVolumeCm3();
                }
                if ($loc->getMaxWeightKg() !== null) {
                    $zoneMaxWgt = ($zoneMaxWgt ?? 0.0) + $loc->getMaxWeightKg();
                }

                $locUsage   = $this->capacityRepository->getLocationUsage((int) $loc->getId());
                $locMetrics[] = [
                    'id'              => $loc->getId(),
                    'name'            => $loc->getName(),
                    'code'            => $loc->getCode(),
                    'capacity_volume' => $this->buildVolumeMetrics($loc->getVolumeCm3(), $locUsage['used_volume_cm3']),
                    'capacity_weight' => $this->buildWeightMetrics($loc->getMaxWeightKg(), $locUsage['used_weight_kg']),
                ];
            }

            // Acumular máximos de la zona al total del almacén
            if ($zoneMaxVol !== null) {
                $totalMaxVol = ($totalMaxVol ?? 0.0) + $zoneMaxVol;
            }
            if ($zoneMaxWgt !== null) {
                $totalMaxWgt = ($totalMaxWgt ?? 0.0) + $zoneMaxWgt;
            }

            $zoneMetrics[] = [
                'id'              => $zone->getId(),
                'name'            => $zone->getName(),
                'code'            => $zone->getCode(),
                'capacity_volume' => $this->buildVolumeMetrics($zoneMaxVol, $zoneUsage['used_volume_cm3']),
                'capacity_weight' => $this->buildWeightMetrics($zoneMaxWgt, $zoneUsage['used_weight_kg']),
                'locations'       => $locMetrics,
            ];
        }

        return [
            'id'              => $warehouse->getId(),
            'name'            => $warehouse->getName(),
            'code'            => $warehouse->getCode(),
            'capacity_volume' => $this->buildVolumeMetrics($totalMaxVol, $warehouseUsage['used_volume_cm3']),
            'capacity_weight' => $this->buildWeightMetrics($totalMaxWgt, $warehouseUsage['used_weight_kg']),
            'zones'           => $zoneMetrics,
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
