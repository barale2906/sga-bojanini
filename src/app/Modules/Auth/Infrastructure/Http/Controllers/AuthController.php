<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use App\Modules\Auth\Application\DTOs\LoginData;
use App\Modules\Auth\Application\UseCases\LoginUseCase;
use App\Modules\Auth\Application\UseCases\LogoutUseCase;
use App\Modules\Auth\Infrastructure\Http\Requests\ChangePasswordRequest;
use App\Modules\Auth\Infrastructure\Http\Requests\LoginRequest;
use App\Modules\Auth\Infrastructure\Http\Resources\UserResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

/**
 * Autenticación de usuarios (Sanctum).
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/v1/auth/login — Iniciar sesión y obtener token Bearer.
     *
     * @response 200 Token y datos del usuario
     * @response 401 Credenciales inválidas
     * @response 422 Validación fallida
     */
    public function login(LoginRequest $request, LoginUseCase $useCase): JsonResponse
    {
        $data = new LoginData(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name', 'api'),
        );

        $result = $useCase->execute($data);

        return $this->success([
            'token' => $result['token'],
            'user'  => new UserResource($result['user']),
        ], 'Login exitoso');
    }

    /**
     * POST /api/v1/auth/logout — Revocar el token actual (requiere autenticación).
     *
     * @response 200 Sesión cerrada
     * @response 401 No autenticado
     */
    public function logout(Request $request, LogoutUseCase $useCase): JsonResponse
    {
        $useCase->execute($request->user());

        return $this->success(null, 'Sesión cerrada exitosamente');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles.permissions');

        return $this->success(new UserResource($user), 'Datos del usuario');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = Hash::make($request->validated('new_password'));
        $user->save();

        return $this->success(null, 'Contraseña actualizada exitosamente');
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->currentAccessToken()->delete();

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $token = $user->createToken('api', $permissions)->plainTextToken;

        return $this->success([
            'token' => $token,
        ], 'Token renovado exitosamente');
    }
}
