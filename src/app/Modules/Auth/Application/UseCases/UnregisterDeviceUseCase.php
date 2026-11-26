<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserDeviceModel;

/**
 * Desvincula un token push del usuario autenticado.
 *
 * Solo borra tokens propios: un usuario no puede desvincular el dispositivo de otro.
 */
class UnregisterDeviceUseCase
{
    /** @return bool true si había un dispositivo propio con ese token. */
    public function execute(int $authenticatedUserId, string $token): bool
    {
        return UserDeviceModel::where('user_id', $authenticatedUserId)
            ->where('token', $token)
            ->delete() > 0;
    }
}
