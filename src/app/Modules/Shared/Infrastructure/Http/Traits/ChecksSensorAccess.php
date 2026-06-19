<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Traits;

use App\Modules\Monitoring\Domain\Services\SensorAccessService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Helpers de autorización por sensor (equipo de monitoreo) para controladores HTTP.
 *
 * Un usuario sin el rol super_administrador solo puede ver/operar los
 * sensores que tenga asignados explícitamente (tabla `user_sensor`).
 *
 * @see SensorAccessService
 */
trait ChecksSensorAccess
{
    private function sensorAccessService(): SensorAccessService
    {
        return app(SensorAccessService::class);
    }

    /** El super_administrador tiene acceso a todos los sensores sin excepción. */
    protected function userHasFullSensorAccess(Authenticatable $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('super_administrador');
    }

    /**
     * IDs de sensores permitidos para el usuario.
     *
     * @return array<int, int>|null `null` significa "sin restricción" (todos los sensores)
     */
    protected function allowedSensorIds(Authenticatable $user): ?array
    {
        return $this->sensorAccessService()->allowedSensorIds(
            (int) $user->getAuthIdentifier(),
            $this->userHasFullSensorAccess($user),
        );
    }

    /**
     * Verifica que el usuario tenga acceso al sensor indicado.
     *
     * @throws AuthorizationException si el usuario no tiene acceso
     */
    protected function assertSensorAccess(Authenticatable $user, int $sensorId): void
    {
        $canAccess = $this->sensorAccessService()->canAccessSensor(
            (int) $user->getAuthIdentifier(),
            $sensorId,
            $this->userHasFullSensorAccess($user),
        );

        if (! $canAccess) {
            throw new AuthorizationException('No tienes acceso al sensor indicado.');
        }
    }

    /** Aplica un `whereIn` con los sensores permitidos, si el usuario tiene restricción. */
    protected function scopeQueryToAllowedSensors(Builder $query, Authenticatable $user, string $column = 'sensor_id'): Builder
    {
        $allowedIds = $this->allowedSensorIds($user);

        if ($allowedIds !== null) {
            $query->whereIn($column, $allowedIds);
        }

        return $query;
    }
}
