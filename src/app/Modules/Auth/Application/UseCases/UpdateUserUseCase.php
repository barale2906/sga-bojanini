<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Application\DTOs\UserData;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Spatie\Permission\Models\Role;

class UpdateUserUseCase
{
    public function execute(int $id, UserData $data): UserModel
    {
        $user = UserModel::findOrFail($id);

        $user->name = $data->name;
        $user->email = $data->email;
        $user->phone = $data->phone;
        $user->is_active = $data->isActive;

        if ($data->password !== null) {
            $user->password = $data->password;
        }

        $user->save();

        if (! empty($data->roleIds)) {
            $roleNames = Role::whereIn('id', $data->roleIds)->pluck('name')->toArray();
            $user->syncRoles($roleNames);
        }

        $user->load('roles.permissions');

        return $user;
    }
}
