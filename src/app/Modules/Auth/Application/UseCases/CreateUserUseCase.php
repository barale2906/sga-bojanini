<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Application\DTOs\UserData;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Monitoring\Domain\Repositories\UserSensorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Warehouse\Domain\Repositories\UserWarehouseRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Spatie\Permission\Models\Role;

class CreateUserUseCase
{
    public function __construct(
        private readonly UserWarehouseRepositoryInterface $warehouseRepository,
        private readonly UserSensorRepositoryInterface $sensorRepository,
    ) {}

    public function execute(UserData $data): UserModel
    {
        $user = UserModel::create([
            'name'      => $data->name,
            'email'     => $data->email,
            'password'  => $data->password,
            'phone'     => $data->phone,
            'is_active' => $data->isActive,
        ]);

        if (! empty($data->roleIds)) {
            $roleNames = Role::whereIn('id', $data->roleIds)->pluck('name')->toArray();
            $user->syncRoles($roleNames);

            if (in_array('super_administrador', $roleNames, true)) {
                $this->assignAllWarehousesAndSensors($user->id);
            }
        }

        $user->load('roles.permissions');

        return $user;
    }

    private function assignAllWarehousesAndSensors(int $userId): void
    {
        $this->warehouseRepository->syncForUser($userId, WarehouseModel::pluck('id')->all());
        $this->sensorRepository->syncForUser($userId, SensorModel::pluck('id')->all());
    }
}
