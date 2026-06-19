<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Traits;

use App\Modules\Warehouse\Domain\Services\WarehouseAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helpers de autorización por almacén para controladores HTTP.
 *
 * Un usuario sin el rol super_administrador solo puede ver/operar los
 * almacenes que tenga asignados explícitamente (tabla `user_warehouse`).
 *
 * @see WarehouseAccessService
 */
trait ChecksWarehouseAccess
{
    private function warehouseAccessService(): WarehouseAccessService
    {
        return app(WarehouseAccessService::class);
    }

    /** El super_administrador tiene acceso a todos los almacenes sin excepción. */
    protected function userHasFullWarehouseAccess(Authenticatable $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super_administrador');
    }

    /**
     * IDs de almacenes permitidos para el usuario.
     *
     * @return array<int, int>|null `null` significa "sin restricción" (todos los almacenes)
     */
    protected function allowedWarehouseIds(Authenticatable $user): ?array
    {
        return $this->warehouseAccessService()->allowedWarehouseIds(
            (int) $user->getAuthIdentifier(),
            $this->userHasFullWarehouseAccess($user),
        );
    }

    /**
     * Verifica que el usuario tenga acceso al almacén indicado.
     *
     * @throws AuthorizationException si el usuario no tiene acceso
     */
    protected function assertWarehouseAccess(Authenticatable $user, int $warehouseId): void
    {
        $canAccess = $this->warehouseAccessService()->canAccessWarehouse(
            (int) $user->getAuthIdentifier(),
            $warehouseId,
            $this->userHasFullWarehouseAccess($user),
        );

        if (! $canAccess) {
            throw new AuthorizationException('No tienes acceso al almacén indicado.');
        }
    }

    /** Aplica un `whereIn` con los almacenes permitidos, si el usuario tiene restricción. */
    protected function scopeQueryToAllowedWarehouses(Builder $query, Authenticatable $user, string $column = 'warehouse_id'): Builder
    {
        $allowedIds = $this->allowedWarehouseIds($user);

        if ($allowedIds !== null) {
            $query->whereIn($column, $allowedIds);
        }

        return $query;
    }
}
