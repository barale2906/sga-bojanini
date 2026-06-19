<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Domain\Services;

use App\Modules\Warehouse\Domain\Repositories\UserWarehouseRepositoryInterface;

/**
 * Resuelve qué almacenes puede ver/operar un usuario.
 *
 * El rol super_administrador (u otro usuario con acceso total) se resuelve
 * fuera de este servicio: el llamador indica `$hasFullAccess` explícitamente
 * para no acoplar este módulo al modelo de Auth/Spatie Permission.
 */
class WarehouseAccessService
{
    public function __construct(
        private readonly UserWarehouseRepositoryInterface $repository,
    ) {}

    /**
     * IDs de almacenes a los que el usuario puede acceder.
     *
     * @return array<int, int>|null `null` significa "sin restricción" (todos los almacenes)
     */
    public function allowedWarehouseIds(int $userId, bool $hasFullAccess): ?array
    {
        return $hasFullAccess ? null : $this->repository->findWarehouseIdsByUser($userId);
    }

    public function canAccessWarehouse(int $userId, int $warehouseId, bool $hasFullAccess): bool
    {
        if ($hasFullAccess) {
            return true;
        }

        return in_array($warehouseId, $this->repository->findWarehouseIdsByUser($userId), true);
    }
}
