<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\DeleteAssetPlaceUseCase;
use App\Modules\Assets\Application\UseCases\DeleteAssetSiteUseCase;
use App\Modules\Assets\Application\UseCases\SaveAssetPlaceUseCase;
use App\Modules\Assets\Application\UseCases\SaveAssetSiteUseCase;
use App\Modules\Assets\Infrastructure\Http\Requests\AssetPlaceRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\AssetSiteRequest;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetPlaceResource;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetSiteResource;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSiteModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Activos — Sedes y sitios
 *
 * Ubicaciones de los activos en dos niveles fijos: Sede → Sitio.
 */
class AssetSiteController extends Controller
{
    use ApiResponse;

    /**
     * Listar sedes con sus sitios (y número de activos por sitio).
     *
     * @queryParam is_active boolean Solo sedes/sitios activos. Example: true
     */
    public function index(Request $request): JsonResponse
    {
        $onlyActive = $request->has('is_active') ? $request->boolean('is_active') : null;

        $sites = AssetSiteModel::query()
            ->when($onlyActive !== null, fn ($q) => $q->where('is_active', $onlyActive))
            ->with(['places' => fn ($q) => $q
                ->when($onlyActive !== null, fn ($p) => $p->where('is_active', $onlyActive))
                ->withCount('assets')
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        return $this->success(AssetSiteResource::collection($sites), 'Sedes y sitios');
    }

    public function store(AssetSiteRequest $request, SaveAssetSiteUseCase $useCase): JsonResponse
    {
        return $this->created(new AssetSiteResource($useCase->execute(null, $request->toData())), 'Sede creada');
    }

    public function update(AssetSiteRequest $request, int $id, SaveAssetSiteUseCase $useCase): JsonResponse
    {
        return $this->success(new AssetSiteResource($useCase->execute($id, $request->toData())), 'Sede actualizada');
    }

    public function destroy(int $id, DeleteAssetSiteUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Sede eliminada');
    }

    public function storePlace(AssetPlaceRequest $request, SaveAssetPlaceUseCase $useCase): JsonResponse
    {
        return $this->created(new AssetPlaceResource($useCase->execute(null, $request->toData())), 'Sitio creado');
    }

    public function updatePlace(AssetPlaceRequest $request, int $id, SaveAssetPlaceUseCase $useCase): JsonResponse
    {
        return $this->success(new AssetPlaceResource($useCase->execute($id, $request->toData())), 'Sitio actualizado');
    }

    public function destroyPlace(int $id, DeleteAssetPlaceUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Sitio eliminado');
    }
}
