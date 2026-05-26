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
use App\Modules\Catalog\Infrastructure\Http\Resources\ProductPresentationResource;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductPresentationController extends Controller
{
    use ApiResponse;

    // ─── CRUD de presentaciones (independientes de producto) ───────────────────

    /**
     * GET /v1/presentations
     * Lista todas las presentaciones disponibles.
     */
    public function index(ListProductPresentationsUseCase $useCase): JsonResponse
    {
        $items = $useCase->execute();

        return $this->success(
            ProductPresentationResource::collection($items),
            'Presentaciones'
        );
    }

    /**
     * GET /v1/presentations/tree
     * Árbol completo de presentaciones (raíces con hijos anidados).
     */
    public function tree(): JsonResponse
    {
        $tree = ProductPresentationModel::with('children.children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success($tree, 'Árbol de presentaciones');
    }

    /**
     * POST /v1/presentations
     * Crea una nueva presentación (sin ligarse a un producto).
     */
    public function store(Request $request, CreateProductPresentationUseCase $useCase): JsonResponse
    {
        $data = $request->validate([
            'parent_id'           => ['nullable', 'integer', 'exists:product_presentations,id'],
            'name'                => ['required', 'string', 'max:255'],
            'code'                => ['required', 'string', 'max:50', 'unique:product_presentations,code'],
            'units_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'quantity_per_parent' => ['nullable', 'integer', 'min:1'],
            'factor_to_base'      => ['required', 'integer', 'min:1'],
            'level'               => ['required', 'integer', 'min:1'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        $presentation = $useCase->execute($data);

        return $this->created(
            new ProductPresentationResource($presentation),
            'Presentación creada'
        );
    }

    /**
     * PUT /v1/presentations/{presentation}
     * Actualiza una presentación existente.
     */
    public function update(int $presentation, Request $request, UpdateProductPresentationUseCase $useCase): JsonResponse
    {
        $data = $request->validate([
            'parent_id'           => ['nullable', 'integer', 'exists:product_presentations,id'],
            'name'                => ['sometimes', 'string', 'max:255'],
            'code'                => ['sometimes', 'string', 'max:50', "unique:product_presentations,code,{$presentation}"],
            'units_of_measure_id' => ['sometimes', 'integer', 'exists:units_of_measure,id'],
            'quantity_per_parent' => ['nullable', 'integer', 'min:1'],
            'factor_to_base'      => ['sometimes', 'integer', 'min:1'],
            'level'               => ['sometimes', 'integer', 'min:1'],
            'is_active'           => ['nullable', 'boolean'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        $entity = $useCase->execute($presentation, $data);

        return $this->success(
            new ProductPresentationResource($entity),
            'Presentación actualizada'
        );
    }

    /**
     * DELETE /v1/presentations/{presentation}
     * Elimina una presentación.
     */
    public function destroy(int $presentation, DeleteProductPresentationUseCase $useCase): JsonResponse
    {
        $useCase->execute($presentation);

        return $this->noContent('Presentación eliminada');
    }

    // ─── Relación producto ↔ presentación ─────────────────────────────────────

    /**
     * GET /v1/products/{productId}/presentations
     * Lista las presentaciones asignadas a un producto.
     */
    public function indexByProduct(int $productId, ListProductPresentationsUseCase $useCase): JsonResponse
    {
        $items = $useCase->executeForProduct($productId);

        return $this->success(
            ProductPresentationResource::collection($items),
            'Presentaciones del producto'
        );
    }

    /**
     * GET /v1/products/{productId}/presentations/tree
     * Árbol de presentaciones asignadas a un producto.
     */
    public function treeByProduct(int $productId): JsonResponse
    {
        $tree = ProductPresentationModel::with('children.children')
            ->whereHas('products', fn ($q) => $q->where('products.id', $productId))
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success($tree, 'Árbol de presentaciones del producto');
    }

    /**
     * POST /v1/products/{productId}/presentations/{presentationId}
     * Asigna una presentación a un producto.
     */
    public function attach(
        int $productId,
        int $presentationId,
        Request $request,
        AttachPresentationToProductUseCase $useCase,
    ): JsonResponse {
        $data = $request->validate([
            'is_purchase_default' => ['nullable', 'boolean'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        $useCase->execute($productId, $presentationId, $data);

        return $this->success(null, 'Presentación asignada al producto');
    }

    /**
     * DELETE /v1/products/{productId}/presentations/{presentationId}
     * Desvincula una presentación de un producto.
     */
    public function detach(
        int $productId,
        int $presentationId,
        DetachPresentationFromProductUseCase $useCase,
    ): JsonResponse {
        $useCase->execute($productId, $presentationId);

        return $this->noContent('Presentación desvinculada del producto');
    }

    // ─── Utilidades ────────────────────────────────────────────────────────────

    public function validateHierarchy(Request $request, PresentationHierarchyValidator $validator): JsonResponse
    {
        $data = $request->validate([
            'parent_id'           => ['nullable', 'integer', 'exists:product_presentations,id'],
            'factor_to_base'      => ['required', 'integer', 'min:1'],
            'quantity_per_parent' => ['nullable', 'integer', 'min:1'],
            'level'               => ['required', 'integer', 'min:1'],
        ]);

        try {
            $validator->validate($data);

            return $this->success(['valid' => true], 'Jerarquía válida');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function convertToBase(Request $request, PresentationConverter $converter): JsonResponse
    {
        $data = $request->validate([
            'presentation_id' => ['required', 'integer', 'exists:product_presentations,id'],
            'quantity'        => ['required', 'integer', 'min:1'],
        ]);

        try {
            $base = $converter->toBase($data['presentation_id'], $data['quantity']);

            return $this->success([
                'quantity_base' => $base,
            ], 'Conversión realizada');
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
