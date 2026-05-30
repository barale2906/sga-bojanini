<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Controllers;

use App\Modules\Shared\Application\UseCases\GetMenuUseCase;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Retorna el árbol de navegación filtrado por los permisos del usuario
 * autenticado. Es la fuente única de verdad que el frontend usa para
 * construir la barra lateral: qué secciones ver y qué acciones mostrar.
 */
class MenuController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly GetMenuUseCase $getMenuUseCase,
    ) {}

    /**
     * GET /api/v1/auth/menu
     *
     * Devuelve el menú de navegación del usuario autenticado.
     * Solo incluye secciones e ítems para los que tiene permiso de lectura.
     * El campo `actions` de cada ítem indica qué operaciones puede ejecutar.
     *
     * @response 200 Árbol de navegación
     * @response 401 Token inválido o ausente
     */
    public function index(Request $request): JsonResponse
    {
        $permissions = $request->user()
            ->getAllPermissions()
            ->pluck('name')
            ->toArray();

        $menu = $this->getMenuUseCase->execute($permissions);

        return $this->success($menu, 'Menú de navegación');
    }
}
