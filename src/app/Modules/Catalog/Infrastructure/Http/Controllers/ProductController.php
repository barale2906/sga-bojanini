<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\ProductData;
use App\Modules\Catalog\Application\UseCases\CreateProductUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteProductUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateProductUseCase;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateProductRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\ProductResource;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request, ProductRepositoryInterface $repository): JsonResponse
    {
        $products = $repository->findAll([
            'category_id'  => $request->query('category_id'),
            'product_type' => $request->query('product_type'),
            'is_active'    => $request->query('is_active'),
            'search'       => $request->query('search'),
        ]);

        return $this->success(
            ProductResource::collection($products),
            'Listado de productos'
        );
    }

    public function store(StoreProductRequest $request, CreateProductUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();
        $product = $useCase->execute(
            $this->toProductData($validated),
            $validated['components'] ?? null,
        );

        return $this->created(
            new ProductResource($product),
            'Producto creado exitosamente'
        );
    }

    public function show(int $product): JsonResponse
    {
        $model = ProductModel::with(['category', 'baseUnit', 'kitComponents.componentProduct'])->find($product);

        if ($model === null) {
            return $this->error('Producto no encontrado', 404);
        }

        return $this->success(
            new ProductResource($model),
            'Detalle del producto'
        );
    }

    public function update(int $product, UpdateProductRequest $request, UpdateProductUseCase $useCase): JsonResponse
    {
        $entity = $useCase->execute($product, $this->toProductData($request->validated()));

        return $this->success(
            new ProductResource($entity),
            'Producto actualizado exitosamente'
        );
    }

    public function destroy(int $product, DeleteProductUseCase $useCase): JsonResponse
    {
        $useCase->execute($product);

        return $this->noContent('Producto eliminado');
    }

    private function toProductData(array $validated): ProductData
    {
        $productType = isset($validated['product_type'])
            ? ProductType::from($validated['product_type'])
            : ProductType::Simple;

        return new ProductData(
            categoryId: $validated['category_id'],
            baseUnitId: $validated['base_unit_id'],
            productType: $productType,
            name: $validated['name'],
            code: $validated['code'],
            sku: $validated['sku'] ?? null,
            description: $validated['description'] ?? null,
            requiresColdChain: (bool) ($validated['requires_cold_chain'] ?? false),
            reorderPoint: (int) ($validated['reorder_point'] ?? 0),
            reorderQuantity: (int) ($validated['reorder_quantity'] ?? 0),
            minStock: (int) ($validated['min_stock'] ?? 0),
            maxStock: (int) ($validated['max_stock'] ?? 0),
        );
    }
}
