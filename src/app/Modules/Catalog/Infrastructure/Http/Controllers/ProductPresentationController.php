<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\UseCases\CreateProductPresentationUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteProductPresentationUseCase;
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

    public function index(int $productId, ListProductPresentationsUseCase $useCase): JsonResponse
    {
        $items = $useCase->execute($productId);

        return $this->success(
            ProductPresentationResource::collection($items),
            'Presentaciones del producto'
        );
    }

    public function tree(int $productId): JsonResponse
    {
        $tree = ProductPresentationModel::with('children.children')
            ->where('product_id', $productId)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->success($tree, 'Árbol de presentaciones');
    }

    public function store(int $productId, Request $request, CreateProductPresentationUseCase $useCase): JsonResponse
    {
        $data = $request->validate([
            'parent_id'           => ['nullable', 'integer', 'exists:product_presentations,id'],
            'name'                => ['required', 'string', 'max:255'],
            'code'                => ['required', 'string', 'max:50'],
            'units_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'quantity_per_parent' => ['nullable', 'integer', 'min:1'],
            'factor_to_base'      => ['required', 'integer', 'min:1'],
            'level'               => ['required', 'integer', 'min:1'],
            'is_purchase_default' => ['nullable', 'boolean'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        $presentation = $useCase->execute($productId, $data);

        return $this->created(
            new ProductPresentationResource($presentation),
            'Presentación creada'
        );
    }

    public function update(int $presentation, Request $request, UpdateProductPresentationUseCase $useCase): JsonResponse
    {
        $data = $request->validate([
            'parent_id'           => ['nullable', 'integer', 'exists:product_presentations,id'],
            'name'                => ['sometimes', 'string', 'max:255'],
            'code'                => ['sometimes', 'string', 'max:50'],
            'units_of_measure_id' => ['sometimes', 'integer', 'exists:units_of_measure,id'],
            'quantity_per_parent' => ['nullable', 'integer', 'min:1'],
            'factor_to_base'      => ['sometimes', 'integer', 'min:1'],
            'level'               => ['sometimes', 'integer', 'min:1'],
            'is_purchase_default' => ['nullable', 'boolean'],
            'is_active'           => ['nullable', 'boolean'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ]);

        $entity = $useCase->execute($presentation, $data);

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

    public function validateHierarchy(Request $request, PresentationHierarchyValidator $validator): JsonResponse
    {
        $data = $request->validate([
            'product_id'          => ['required', 'integer', 'exists:products,id'],
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
