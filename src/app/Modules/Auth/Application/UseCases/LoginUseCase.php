<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Application\DTOs\LoginData;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Support\Facades\Auth;

class LoginUseCase
{
    /**
     * @return array{token: string, user: UserModel}
     */
    public function execute(LoginData $data): array
    {
        if (! Auth::attempt(['email' => $data->email, 'password' => $data->password])) {
            throw new \DomainException('Credenciales inválidas.');
        }

        /** @var UserModel $user */
        $user = UserModel::where('email', $data->email)->first();

        if (! $user->is_active) {
            throw new \DomainException('Tu cuenta está desactivada. Contacta al administrador.');
        }

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        $token = $user->createToken(
            $data->deviceName ?? 'api',
            $permissions
        )->plainTextToken;

        $user->load('roles');

        return [
            'token' => $token,
            'user'  => $user,
        ];
    }
}
