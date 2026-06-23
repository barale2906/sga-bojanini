<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Warehouse\Application\DTOs\LocationData;
use App\Modules\Warehouse\Application\DTOs\ZoneData;
use App\Modules\Warehouse\Application\UseCases\CreateLocationUseCase;
use App\Modules\Warehouse\Application\UseCases\CreateZoneUseCase;
use App\Modules\Warehouse\Domain\Repositories\CapacityRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;

/**
 * Resuelve automáticamente dónde debe quedar almacenada una entrada de
 * inventario inicial:
 *
 * - En el almacén indicado por el usuario al importar, o en el primer
 *   almacén activo registrado en el sistema si no se indica ninguno.
 * - Dentro de ese almacén, en su primera zona activa — salvo que el
 *   producto requiera cadena de frío, en cuyo caso se usa (o se crea) la
 *   zona de tipo "cold" del almacén.
 * - En un estante (location) con capacidad disponible; si ninguno la
 *   tiene, se crea uno nuevo con capacidad estándar de 3 m³ / 500 kg.
 *
 * Reutiliza los casos de uso de creación del módulo Warehouse para no
 * duplicar las validaciones de unicidad de código que ya aplican allí.
 */
class StorageAllocationService
{
    private const SHELF_VOLUME_CM3 = 3_000_000.0; // 3 m³ = 3.000.000 cm³

    private const SHELF_MAX_WEIGHT_KG = 500.0;

    private const COLD_ZONE_TYPE = 'cold';

    public function __construct(
        private readonly CapacityRepositoryInterface $capacityRepository,
        private readonly CreateZoneUseCase $createZoneUseCase,
        private readonly CreateLocationUseCase $createLocationUseCase,
    ) {}

    /**
     * Resuelve el almacén destino: el indicado por `$warehouseId` si se
     * proporciona, o el primer almacén activo del sistema en caso
     * contrario.
     */
    public function resolveWarehouse(?int $warehouseId): WarehouseModel
    {
        return $warehouseId !== null
            ? $this->resolveWarehouseById($warehouseId)
            : $this->resolveDefaultWarehouse();
    }

    /** @throws \DomainException si el almacén no existe o está inactivo */
    private function resolveWarehouseById(int $warehouseId): WarehouseModel
    {
        $warehouse = WarehouseModel::where('id', $warehouseId)->where('is_active', true)->first();

        if ($warehouse === null) {
            throw new \DomainException("El almacén seleccionado (ID {$warehouseId}) no existe o está inactivo.");
        }

        return $warehouse;
    }

    /**
     * Primer almacén activo del sistema (ordenado por `id`), usado como
     * destino cuando la importación no especifica `warehouse_id`.
     *
     * @throws \DomainException si no hay ningún almacén activo registrado
     */
    public function resolveDefaultWarehouse(): WarehouseModel
    {
        $warehouse = WarehouseModel::where('is_active', true)->orderBy('id')->first();

        if ($warehouse === null) {
            throw new \DomainException('No hay almacenes registrados en el sistema. Cree un almacén antes de importar entradas iniciales.');
        }

        return $warehouse;
    }

    /** Zona destino según si el producto requiere o no cadena de frío. */
    public function resolveTargetZone(int $warehouseId, bool $requiresColdChain): ZoneModel
    {
        return $requiresColdChain
            ? $this->resolveColdZone($warehouseId)
            : $this->resolveDefaultZone($warehouseId);
    }

    /**
     * Primera zona activa del almacén (ordenada por `id`).
     *
     * @throws \DomainException si el almacén no tiene ninguna zona activa
     */
    private function resolveDefaultZone(int $warehouseId): ZoneModel
    {
        $zone = ZoneModel::where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($zone === null) {
            throw new \DomainException('El almacén destino no tiene zonas registradas. Cree al menos una zona antes de importar entradas iniciales.');
        }

        return $zone;
    }

    /**
     * Primera zona de tipo `cold` del almacén; si no existe ninguna, la
     * crea automáticamente (nombre "Zona Refrigerada", código `REF-NNN`).
     */
    private function resolveColdZone(int $warehouseId): ZoneModel
    {
        $zone = ZoneModel::where('warehouse_id', $warehouseId)
            ->where('type', self::COLD_ZONE_TYPE)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($zone !== null) {
            return $zone;
        }

        $created = $this->createZoneUseCase->execute(new ZoneData(
            warehouseId: $warehouseId,
            name: 'Zona Refrigerada',
            code: $this->nextCode(
                fn (string $code) => ZoneModel::where('warehouse_id', $warehouseId)->where('code', $code)->exists(),
                'REF',
            ),
            type: self::COLD_ZONE_TYPE,
            description: 'Zona generada automáticamente para productos que requieren cadena de frío.',
        ));

        return ZoneModel::findOrFail($created->getId());
    }

    /**
     * Busca un estante con capacidad disponible para el volumen/peso
     * indicados dentro de la zona; si ninguno alcanza, crea uno nuevo con
     * la capacidad estándar.
     *
     * @throws \DomainException si la cantidad excede la capacidad máxima de un estante completo
     */
    public function allocateLocation(int $zoneId, float $neededVolumeCm3, float $neededWeightKg): LocationModel
    {
        if ($neededVolumeCm3 > self::SHELF_VOLUME_CM3 || $neededWeightKg > self::SHELF_MAX_WEIGHT_KG) {
            throw new \DomainException(sprintf(
                'La cantidad de esta fila excede la capacidad máxima de un estante (%s m³ / %s kg). '
                .'Divida la entrada en varias filas con una cantidad menor.',
                self::SHELF_VOLUME_CM3 / 1_000_000,
                self::SHELF_MAX_WEIGHT_KG,
            ));
        }

        $locations = LocationModel::where('zone_id', $zoneId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($locations as $location) {
            $usage = $this->capacityRepository->getLocationUsage($location->id);

            $availableVolume = (float) ($location->volume_cm3 ?? 0) - $usage['used_volume_cm3'];
            $availableWeight = (float) ($location->max_weight_kg ?? 0) - $usage['used_weight_kg'];

            if ($availableVolume >= $neededVolumeCm3 && $availableWeight >= $neededWeightKg) {
                return $location;
            }
        }

        $code = $this->nextCode(
            fn (string $code) => LocationModel::where('zone_id', $zoneId)->where('code', $code)->exists(),
            'EST',
            $locations->count() + 1,
        );

        $created = $this->createLocationUseCase->execute(new LocationData(
            zoneId: $zoneId,
            name: "Estante {$code}",
            code: $code,
            volumeCm3: self::SHELF_VOLUME_CM3,
            maxWeightKg: self::SHELF_MAX_WEIGHT_KG,
            description: 'Estante generado automáticamente durante la carga de inventario inicial.',
        ));

        return LocationModel::findOrFail($created->getId());
    }

    /** Genera el primer código `{prefix}-{NNN}` que no exista, evaluando `$exists` empezando desde `$startAt`. */
    private function nextCode(callable $exists, string $prefix, int $startAt = 1): string
    {
        $suffix = $startAt;

        do {
            $code = sprintf('%s-%03d', $prefix, $suffix);
            $suffix++;
        } while ($exists($code));

        return $code;
    }
}
