<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Services;

use App\Modules\Monitoring\Domain\Repositories\UserSensorRepositoryInterface;

/**
 * Resuelve qué sensores (equipos de monitoreo) puede ver/operar un usuario.
 *
 * El rol super_administrador (u otro usuario con acceso total) se resuelve
 * fuera de este servicio: el llamador indica `$hasFullAccess` explícitamente
 * para no acoplar este módulo al modelo de Auth/Spatie Permission.
 */
class SensorAccessService
{
    public function __construct(
        private readonly UserSensorRepositoryInterface $repository,
    ) {}

    /**
     * IDs de sensores a los que el usuario puede acceder.
     *
     * @return array<int, int>|null `null` significa "sin restricción" (todos los sensores)
     */
    public function allowedSensorIds(int $userId, bool $hasFullAccess): ?array
    {
        return $hasFullAccess ? null : $this->repository->findSensorIdsByUser($userId);
    }

    public function canAccessSensor(int $userId, int $sensorId, bool $hasFullAccess): bool
    {
        if ($hasFullAccess) {
            return true;
        }

        return in_array($sensorId, $this->repository->findSensorIdsByUser($userId), true);
    }
}
