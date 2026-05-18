<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Application\DTOs\UserData;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Spatie\Permission\Models\Role;

class CreateUserUseCase
{
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
        }

        $user->load('roles.permissions');

        return $user;
    }
}
