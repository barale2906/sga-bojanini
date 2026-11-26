<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserDeviceModel;

/**
 * Registra (o actualiza) el token push de un dispositivo para el usuario
 * autenticado.
 *
 * El token es único: si el teléfono lo usaba otro usuario (teléfonos
 * compartidos de la clínica) se reasigna al actual, de modo que el usuario
 * anterior deja de recibir sus notificaciones en ese teléfono.
 */
class RegisterDeviceUseCase
{
    public function execute(int $authenticatedUserId, string $token, string $platform, ?string $deviceName): UserDeviceModel
    {
        return UserDeviceModel::updateOrCreate(
            ['token' => $token],
            [
                'user_id'      => $authenticatedUserId,
                'platform'     => $platform,
                'device_name'  => $deviceName,
                'last_seen_at' => now(),
            ],
        );
    }
}
