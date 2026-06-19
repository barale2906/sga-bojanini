<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Domain\Repositories;

/**
 * Gestiona la relación many-to-many entre usuarios y sensores (equipos de
 * monitoreo) que sustenta el control de acceso por sensor (ver SensorAccessService).
 */
interface UserSensorRepositoryInterface
{
    /**
     * IDs de los sensores asignados explícitamente a un usuario.
     *
     * @return array<int, int>
     */
    public function findSensorIdsByUser(int $userId): array;

    /**
     * IDs de los usuarios con acceso explícito a un sensor.
     *
     * @return array<int, int>
     */
    public function findUserIdsBySensor(int $sensorId): array;

    /**
     * Reemplaza el conjunto completo de sensores asignados a un usuario.
     *
     * @param  array<int, int>  $sensorIds
     * @return array<int, int> IDs de sensores que quedaron asignados
     */
    public function syncForUser(int $userId, array $sensorIds): array;

    /** Asigna un sensor a todos los usuarios que tengan el rol indicado. */
    public function assignSensorToUsersWithRole(int $sensorId, string $roleName): void;
}
