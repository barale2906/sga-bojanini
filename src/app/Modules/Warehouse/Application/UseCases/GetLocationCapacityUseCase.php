<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Application\UseCases;

use App\Modules\Warehouse\Domain\Repositories\CapacityRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;

class GetLocationCapacityUseCase
{
    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly CapacityRepositoryInterface $capacityRepository,
    ) {}

    /**
     * Retorna los métricas de capacidad de una ubicación.
     *
     * @return array{
     *   id: int,
     *   name: string,
     *   code: string,
     *   zone_id: int,
     *   capacity_volume: array{max_cm3: float|null, used_cm3: float, available_cm3: float|null, usage_pct: float|null},
     *   capacity_weight: array{max_kg: float|null, used_kg: float, available_kg: float|null, usage_pct: float|null}
     * }
     */
    public function execute(int $locationId): array
    {
        $location = $this->locationRepository->findById($locationId);

        if ($location === null) {
            throw new \DomainException('Ubicación no encontrada.');
        }

        $usage = $this->capacityRepository->getLocationUsage($locationId);

        return [
            'id'      => $location->getId(),
            'name'    => $location->getName(),
            'code'    => $location->getCode(),
            'zone_id' => $location->getZoneId(),
            'capacity_volume' => $this->buildVolumeMetrics(
                $location->getVolumeCm3(),
                $usage['used_volume_cm3']
            ),
            'capacity_weight' => $this->buildWeightMetrics(
                $location->getMaxWeightKg(),
                $usage['used_weight_kg']
            ),
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
