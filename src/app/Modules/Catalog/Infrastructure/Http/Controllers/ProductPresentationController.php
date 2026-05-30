<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\UseCases\AttachPresentationToProductUseCase;
use App\Modules\Catalog\Application\UseCases\CreateProductPresentationUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteProductPresentationUseCase;
use App\Modules\Catalog\Application\UseCases\DetachPresentationFromProductUseCase;
use App\Modules\Catalog\Application\UseCases\ListProductPresentationsUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateProductPresentationUseCase;
use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Catalog\Domain\Services\PresentationHierarchyValidator;
use App\Modules\Catalog\Infrastructure\Http\Requests\AttachPresentationToProductRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\ConvertPresentationToBaseRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreProductPresentationRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateProductPresentationRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\ValidatePresentationHierarchyRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\ProductPresentationResource;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ProductPresentationController extends Controller
{
    use ApiResponse;

    // ─── CRUD de presentaciones (independientes de producto) ───────────────────

    public function index(ListProductPresentationsUseCase $useCase): JsonResponse
    {
        $items = $useCase->execute();

        return $this->success(
            ProductPresentationResource::collection($items),
            'Presentaciones'
        );
    }

    public function tree(): JsonResponse
    {
        $tree = ProductPresentationModel::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success($tree, 'Árbol de presentaciones');
    }

    public function store(StoreProductPresentationRequest $request, CreateProductPresentationUseCase $useCase): JsonResponse
    {
        $presentation = $useCase->execute($request->validated());

        return $this->created(
            new ProductPresentationResource($presentation),
            'Presentación creada'
        );
    }

    public function update(int $presentation, UpdateProductPresentationRequest $request, UpdateProductPresentationUseCase $useCase): JsonResponse
    {
        $entity = $useCase->execute($presentation, $request->validated());

        return $this->success(
            new ProductPresentationResource($entity),
            'Presentación actualizada'
        );
    }

    public function destroy(int $presentation, DeleteProductPresentationUseCase $useCase): JsonResponse
    {
        $useCase->execute($presentation);

        return $this->noContent('Presentación eliminada');
    }

    // ─── Relación producto ↔ presentación ─────────────────────────────────────

    public function indexByProduct(int $productId, ListProductPresentationsUseCase $useCase): JsonResponse
    {
        $items = $useCase->executeForProduct($productId);

        return $this->success(
            ProductPresentationResource::collection($items),
            'Presentaciones del producto'
        );
    }

    public function treeByProduct(int $productId): JsonResponse
    {
        $tree = ProductPresentationModel::with('children.children')
            ->whereHas('products', fn ($q) => $q->where('products.id', $productId))
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success($tree, 'Árbol de presentaciones del producto');
    }

    public function attach(
        int $productId,
        int $presentationId,
        AttachPresentationToProductRequest $request,
        AttachPresentationToProductUseCase $useCase,
    ): JsonResponse {
        $useCase->execute($productId, $presentationId, $request->validated());

        return $this->success(null, 'Presentación asignada al producto');
    }

    public function detach(
        int $productId,
        int $presentationId,
        DetachPresentationFromProductUseCase $useCase,
    ): JsonResponse {
        $useCase->execute($productId, $presentationId);

        return $this->noContent('Presentación desvinculada del producto');
    }

    // ─── Utilidades ────────────────────────────────────────────────────────────

    public function validateHierarchy(ValidatePresentationHierarchyRequest $request, PresentationHierarchyValidator $validator): JsonResponse
    {
        try {
            $validator->validate($request->validated());

            return $this->success(['valid' => true], 'Jerarquía válida');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function convertToBase(ConvertPresentationToBaseRequest $request, PresentationConverter $converter): JsonResponse
    {
        try {
            $base = $converter->toBase(
                $request->validated('presentation_id'),
                $request->validated('quantity'),
            );

            return $this->success(['quantity_base' => $base], 'Conversión realizada');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
