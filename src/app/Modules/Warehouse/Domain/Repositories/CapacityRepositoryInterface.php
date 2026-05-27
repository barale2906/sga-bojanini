<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Repositories;

/**
 * Puerto de dominio para consultas de uso de capacidad física
 * (volumen cm³ y peso kg) en la jerarquía almacén → zona → ubicación.
 *
 * Cada método devuelve un array con la estructura:
 *   used_volume_cm3  float|null
 *   used_weight_kg   float|null
 */
interface CapacityRepositoryInterface
{
    /**
     * Retorna el volumen (cm³) y el peso (kg) actualmente ocupados en una ubicación.
     * Solo considera lotes con status 'active'.
     *
     * @return array{used_volume_cm3: float, used_weight_kg: float}
     */
    public function getLocationUsage(int $locationId): array;

    /**
     * Retorna el uso agregado de todas las ubicaciones de una zona.
     *
     * @return array{used_volume_cm3: float, used_weight_kg: float}
     */
    public function getZoneUsage(int $zoneId): array;

    /**
     * Retorna el uso agregado de todas las ubicaciones de un almacén.
     *
     * @return array{used_volume_cm3: float, used_weight_kg: float}
     */
    public function getWarehouseUsage(int $warehouseId): array;
}
