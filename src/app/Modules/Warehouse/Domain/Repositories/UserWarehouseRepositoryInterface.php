<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Repositories;

/**
 * Gestiona la relación many-to-many entre usuarios y almacenes que sustenta
 * el control de acceso por almacén (ver WarehouseAccessService).
 */
interface UserWarehouseRepositoryInterface
{
    /**
     * IDs de los almacenes asignados explícitamente a un usuario.
     *
     * @return array<int, int>
     */
    public function findWarehouseIdsByUser(int $userId): array;

    /**
     * IDs de los usuarios con acceso explícito a un almacén.
     *
     * @return array<int, int>
     */
    public function findUserIdsByWarehouse(int $warehouseId): array;

    /**
     * Reemplaza el conjunto completo de almacenes asignados a un usuario.
     *
     * @param array<int, int> $warehouseIds
     *
     * @return array<int, int> IDs de almacenes que quedaron asignados
     */
    public function syncForUser(int $userId, array $warehouseIds): array;

    /** Asigna un almacén a todos los usuarios que tengan el rol indicado. */
    public function assignWarehouseToUsersWithRole(int $warehouseId, string $roleName): void;
}
