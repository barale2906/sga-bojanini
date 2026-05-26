<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\UseCases\SyncKitComponentsUseCase;
use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Services\KitExplosionService;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductKitComponentController extends Controller
{
    use ApiResponse;

    public function index(int $productId, ProductKitComponentRepositoryInterface $repository): JsonResponse
    {
        $components = $repository->findWithDetailsByKitProductId($productId, false);

        return $this->success($components, 'Componentes del kit');
    }

    public function sync(int $productId, Request $request, SyncKitComponentsUseCase $useCase): JsonResponse
    {
        $data = $request->validate([
            'components'                        => ['required', 'array', 'min:1'],
            'components.*.component_product_id' => ['required', 'integer', 'exists:products,id'],
            'components.*.quantity_per_kit'     => ['required', 'integer', 'min:1'],
            'components.*.sort_order'           => ['nullable', 'integer', 'min:0'],
            'components.*.notes'                => ['nullable', 'string', 'max:500'],
        ]);

        $saved = $useCase->execute($productId, $data['components']);

        return $this->success($saved, 'Receta del kit sincronizada');
    }

    public function destroy(int $productId, int $componentId, ProductKitComponentRepositoryInterface $repository): JsonResponse
    {
        $deleted = $repository->deleteById($componentId, $productId);

        if (! $deleted) {
            return $this->error('Componente no encontrado en este kit.', 404);
        }

        return $this->success(null, 'Componente eliminado del kit.');
    }

    public function explode(int $productId, Request $request, KitExplosionService $service): JsonResponse
    {
        $data = $request->validate([
            'quantity_kits' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $lines = $service->explode($productId, $data['quantity_kits']);

            return $this->success($lines, 'Explosión del kit');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
