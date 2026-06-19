<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Warehouse\Domain\Repositories\UserWarehouseRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;

class EloquentUserWarehouseRepository implements UserWarehouseRepositoryInterface
{
    public function findWarehouseIdsByUser(int $userId): array
    {
        $user = UserModel::find($userId);

        return $user?->warehouses()->pluck('warehouses.id')->all() ?? [];
    }

    public function findUserIdsByWarehouse(int $warehouseId): array
    {
        $warehouse = WarehouseModel::find($warehouseId);

        return $warehouse?->users()->pluck('users.id')->all() ?? [];
    }

    public function syncForUser(int $userId, array $warehouseIds): array
    {
        $user = UserModel::findOrFail($userId);
        $user->warehouses()->sync($warehouseIds);

        return $user->warehouses()->pluck('warehouses.id')->all();
    }

    public function assignWarehouseToUsersWithRole(int $warehouseId, string $roleName): void
    {
        UserModel::role($roleName)->get()->each(
            fn (UserModel $user) => $user->warehouses()->syncWithoutDetaching([$warehouseId])
        );
    }
}
