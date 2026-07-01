<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Persistence;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Monitoring\Domain\Repositories\UserSensorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use Illuminate\Database\Eloquent\Builder;

class EloquentUserSensorRepository implements UserSensorRepositoryInterface
{
    public function findSensorIdsByUser(int $userId): array
    {
        $user = UserModel::find($userId);

        return $user?->sensors()->pluck('sensors.id')->all() ?? [];
    }

    public function findUserIdsBySensor(int $sensorId): array
    {
        $sensor = SensorModel::find($sensorId);

        return $sensor?->users()->pluck('users.id')->all() ?? [];
    }

    public function syncForUser(int $userId, array $sensorIds): array
    {
        $user = UserModel::findOrFail($userId);
        $user->sensors()->sync($sensorIds);

        return $user->sensors()->pluck('sensors.id')->all();
    }

    public function assignSensorToUsersWithRole(int $sensorId, string $roleName): void
    {
        UserModel::role($roleName)->get()->each(
            fn (UserModel $user) => $user->sensors()->syncWithoutDetaching([$sensorId])
        );
    }

    public function assignSensorToUsers(int $sensorId, array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        UserModel::whereIn('id', $userIds)->get()->each(
            fn (UserModel $user) => $user->sensors()->syncWithoutDetaching([$sensorId])
        );
    }

    public function syncForUserByWarehouses(int $userId, array $warehouseIds): void
    {
        $sensorIds = empty($warehouseIds)
            ? []
            : SensorModel::whereHas(
                'zone',
                fn (Builder $q) => $q->whereIn('warehouse_id', $warehouseIds)
            )->pluck('id')->all();

        UserModel::findOrFail($userId)->sensors()->sync($sensorIds);
    }
}
