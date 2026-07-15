<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\UseCases\SyncKitComponentsUseCase;
use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;
use App\Modules\Catalog\Domain\Services\KitExplosionService;
use App\Modules\Catalog\Infrastructure\Http\Requests\ExplodeKitRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\SyncKitComponentsRequest;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ProductKitComponentController extends Controller
{
    use ApiResponse;

    public function index(int $genericId, ProductKitComponentRepositoryInterface $repository): JsonResponse
    {
        $components = $repository->findWithDetailsByKitGenericId($genericId, false);

        return $this->success($components, 'Componentes del kit');
    }

    public function sync(int $genericId, SyncKitComponentsRequest $request, SyncKitComponentsUseCase $useCase): JsonResponse
    {
        $saved = $useCase->execute($genericId, $request->validated('components'));

        return $this->success($saved, 'Receta del kit sincronizada');
    }

    public function destroy(int $genericId, int $componentId, ProductKitComponentRepositoryInterface $repository): JsonResponse
    {
        $deleted = $repository->deleteById($componentId, $genericId);

        if (! $deleted) {
            return $this->error('Componente no encontrado en este kit.', 404);
        }

        return $this->success(null, 'Componente eliminado del kit.');
    }

    public function explode(int $genericId, ExplodeKitRequest $request, KitExplosionService $service): JsonResponse
    {
        try {
            $lines = $service->explode($genericId, $request->validated('quantity_kits'));

            return $this->success($lines, 'Explosión del kit');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
