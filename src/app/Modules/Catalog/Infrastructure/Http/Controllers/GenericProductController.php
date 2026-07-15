<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\GenericProductData;
use App\Modules\Catalog\Application\UseCases\CreateGenericProductUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteGenericProductUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateGenericProductUseCase;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreGenericProductRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateGenericProductRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\GenericProductResource;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Services\BarcodeService;
use App\Modules\Inventory\Domain\Services\KitAvailabilityService;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class GenericProductController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function index(Request $request, GenericProductRepositoryInterface $repository): JsonResponse
    {
        $products = $repository->findAll([
            'category_id'  => $request->query('category_id'),
            'product_type' => $request->query('product_type'),
            'is_active'    => $request->query('is_active'),
            'search'       => $request->query('search'),
        ]);

        return $this->success(
            GenericProductResource::collection($products),
            'Listado de productos genéricos'
        );
    }

    public function store(StoreGenericProductRequest $request, CreateGenericProductUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();
        $product   = $useCase->execute(
            $this->toGenericProductData($validated),
            $validated['components'] ?? null,
        );

        return $this->created(
            new GenericProductResource($product),
            'Producto genérico creado exitosamente'
        );
    }

    public function show(int $id): JsonResponse
    {
        $model = GenericProductModel::with([
            'category',
            'baseUnit',
            'classification',
            'kitComponents.componentGeneric',
            'variants.sanitaryRegistrations',
        ])->find($id);

        if ($model === null) {
            return $this->error('Producto genérico no encontrado', 404);
        }

        return $this->success(
            new GenericProductResource($model),
            'Detalle del producto genérico'
        );
    }

    public function update(int $id, UpdateGenericProductRequest $request, UpdateGenericProductUseCase $useCase): JsonResponse
    {
        $entity = $useCase->execute($id, $this->toGenericProductData($request->validated()));

        return $this->success(
            new GenericProductResource($entity),
            'Producto genérico actualizado exitosamente'
        );
    }

    public function destroy(int $id, DeleteGenericProductUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Producto genérico eliminado');
    }

    public function kitAvailability(int $id, Request $request, KitAvailabilityService $service): JsonResponse
    {
        $model = GenericProductModel::find($id);

        if ($model === null) {
            return $this->error('Producto genérico no encontrado', 404);
        }

        if ($model->product_type !== ProductType::Kit->value) {
            return $this->error('El producto no es de tipo kit.', 422);
        }

        $this->assertWarehouseAccess($request->user(), $request->integer('warehouse_id'));

        $available = $service->getAvailableKits($id, $request->integer('warehouse_id'));

        return $this->success([
            'generic_product_id' => $id,
            'warehouse_id'       => $request->integer('warehouse_id'),
            'available_kits'     => $available,
        ], 'Disponibilidad del kit');
    }

    public function barcodeSvg(int $id, BarcodeService $service): Response
    {
        $model = GenericProductModel::find($id);

        if ($model === null) {
            abort(404, 'Producto genérico no encontrado');
        }

        $svg = $service->renderSvg((string) $model->barcode);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function barcodePrint(int $id, BarcodeService $service): Response
    {
        $model = GenericProductModel::with('category', 'baseUnit')->find($id);

        if ($model === null) {
            abort(404, 'Producto genérico no encontrado');
        }

        $product = app(GenericProductRepositoryInterface::class)->findById($id);
        $html    = $service->renderPrintableHtml($product);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function barcodeList(Request $request, GenericProductRepositoryInterface $repository, BarcodeService $service): Response
    {
        $filters = [
            'is_active'    => $request->query('active', '1'),
            'category_id'  => $request->query('category_id'),
        ];

        $products = $repository->findAll($filters);
        $html     = $service->renderListHtml($products);

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function findByBarcode(string $value, GenericProductRepositoryInterface $repository): JsonResponse
    {
        $product = $repository->findByBarcode($value);

        if ($product === null) {
            return $this->error('Producto no encontrado para el código escaneado', 404);
        }

        return $this->success(
            new GenericProductResource($product),
            'Producto encontrado'
        );
    }

    private function toGenericProductData(array $validated): GenericProductData
    {
        return new GenericProductData(
            categoryId:        $validated['category_id'],
            baseUnitId:        $validated['base_unit_id'],
            productType:       isset($validated['product_type'])
                ? ProductType::from($validated['product_type'])
                : ProductType::Simple,
            name:              $validated['name'],
            classificationId:  isset($validated['classification_id']) ? (int) $validated['classification_id'] : null,
            description:       $validated['description'] ?? null,
            concentration:     $validated['concentration'] ?? null,
            pharmaceuticalForm: $validated['pharmaceutical_form'] ?? null,
            volumeCm3:         isset($validated['volume_cm3']) ? (float) $validated['volume_cm3'] : null,
            weightKg:          isset($validated['weight_kg'])  ? (float) $validated['weight_kg']  : null,
            requiresColdChain: (bool) ($validated['requires_cold_chain'] ?? false),
            reorderPoint:      (int) ($validated['reorder_point']    ?? 0),
            reorderQuantity:   (int) ($validated['reorder_quantity'] ?? 0),
            minStock:          (int) ($validated['min_stock']        ?? 0),
            maxStock:          (int) ($validated['max_stock']        ?? 0),
        );
    }
}
