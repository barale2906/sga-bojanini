<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\DeleteAssetCategoryUseCase;
use App\Modules\Assets\Application\UseCases\ListAssetCategoriesUseCase;
use App\Modules\Assets\Application\UseCases\SaveAssetCategoryUseCase;
use App\Modules\Assets\Infrastructure\Http\Requests\AssetCategoryRequest;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetCategoryResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Activos — Categorías
 *
 * Categorías de activos. El tipo (`biomedical`, `computing`, `general`)
 * define si sus activos llevan hoja de vida y mantenimiento.
 */
class AssetCategoryController extends Controller
{
    use ApiResponse;

    /**
     * Listar categorías.
     *
     * @queryParam asset_type string biomedical | computing | general. Example: biomedical
     * @queryParam is_active boolean Filtrar por estado. Example: true
     * @queryParam search string Código o nombre. Example: BIO
     */
    public function index(Request $request, ListAssetCategoriesUseCase $useCase): JsonResponse
    {
        $items = $useCase->execute($request->only(['asset_type', 'is_active', 'search']));

        return $this->success(AssetCategoryResource::collection($items), 'Categorías de activos');
    }

    /**
     * Crear categoría.
     *
     * @response 409 {"success": false, "message": "Ya existe una categoría de activos con el código 'BIO'."}
     */
    public function store(AssetCategoryRequest $request, SaveAssetCategoryUseCase $useCase): JsonResponse
    {
        return $this->created(new AssetCategoryResource($useCase->execute(null, $request->toData())), 'Categoría creada');
    }

    /** Actualizar categoría. */
    public function update(AssetCategoryRequest $request, int $id, SaveAssetCategoryUseCase $useCase): JsonResponse
    {
        return $this->success(new AssetCategoryResource($useCase->execute($id, $request->toData())), 'Categoría actualizada');
    }

    /**
     * Eliminar categoría (solo si no tiene activos).
     *
     * @response 409 {"success": false, "message": "No se puede eliminar la categoría porque tiene activos asociados. Desactívela en su lugar."}
     */
    public function destroy(int $id, DeleteAssetCategoryUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Categoría eliminada');
    }
}
