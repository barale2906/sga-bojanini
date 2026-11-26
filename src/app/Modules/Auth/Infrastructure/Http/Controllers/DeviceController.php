<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use App\Modules\Auth\Application\UseCases\RegisterDeviceUseCase;
use App\Modules\Auth\Application\UseCases\UnregisterDeviceUseCase;
use App\Modules\Auth\Infrastructure\Http\Requests\RegisterDeviceRequest;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Dispositivos móviles del usuario autenticado (token push FCM).
 *
 * Ninguna ruta recibe un ID de usuario: solo se opera sobre `$request->user()`.
 */
class DeviceController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/v1/auth/devices — Registra o actualiza el token push del dispositivo.
     *
     * @response 200 Dispositivo registrado
     * @response 401 No autenticado
     * @response 422 Validación
     */
    public function store(RegisterDeviceRequest $request, RegisterDeviceUseCase $useCase): JsonResponse
    {
        $useCase->execute(
            (int) $request->user()->id,
            $request->validated('token'),
            $request->validated('platform'),
            $request->validated('device_name'),
        );

        return $this->success(null, 'Dispositivo registrado');
    }

    /**
     * DELETE /api/v1/auth/devices/{token} — Desvincula el token push del usuario.
     *
     * @response 200 Dispositivo desvinculado
     * @response 401 No autenticado
     * @response 404 El token no pertenece al usuario
     */
    public function destroy(Request $request, string $token, UnregisterDeviceUseCase $useCase): JsonResponse
    {
        if (! $useCase->execute((int) $request->user()->id, $token)) {
            return $this->error('Dispositivo no encontrado', 404);
        }

        return $this->success(null, 'Dispositivo desvinculado');
    }
}
