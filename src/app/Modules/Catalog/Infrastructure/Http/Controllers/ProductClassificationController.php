<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\ProductClassificationData;
use App\Modules\Catalog\Application\UseCases\CreateProductClassificationUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteProductClassificationUseCase;
use App\Modules\Catalog\Application\UseCases\ListProductClassificationsUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateProductClassificationUseCase;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreProductClassificationRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateProductClassificationRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\ProductClassificationResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductClassificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request, ListProductClassificationsUseCase $useCase): JsonResponse
    {
        $classifications = $useCase->execute([
            'is_active' => $request->query('is_active'),
            'search'    => $request->query('search'),
        ]);

        return $this->success(
            ProductClassificationResource::collection($classifications),
            'Listado de clasificaciones de producto'
        );
    }

    public function show(int $product_classification, ProductClassificationRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($product_classification);

        if ($entity === null) {
            return $this->error('Clasificación no encontrada', 404);
        }

        return $this->success(
            new ProductClassificationResource($entity),
            'Detalle de clasificación'
        );
    }

    public function store(StoreProductClassificationRequest $request, CreateProductClassificationUseCase $useCase): JsonResponse
    {
        $classification = $useCase->execute($this->toData($request->validated()));

        return $this->created(
            new ProductClassificationResource($classification),
            'Clasificación creada exitosamente'
        );
    }

    public function update(int $product_classification, UpdateProductClassificationRequest $request, UpdateProductClassificationUseCase $useCase): JsonResponse
    {
        $entity = $useCase->execute($product_classification, $this->toData($request->validated()));

        return $this->success(
            new ProductClassificationResource($entity),
            'Clasificación actualizada exitosamente'
        );
    }

    public function destroy(int $product_classification, DeleteProductClassificationUseCase $useCase): JsonResponse
    {
        $useCase->execute($product_classification);

        return $this->noContent('Clasificación eliminada');
    }

    private function toData(array $validated): ProductClassificationData
    {
        return new ProductClassificationData(
            code:                    $validated['code'],
            name:                    $validated['name'],
            description:             $validated['description'] ?? null,
            hasSanitaryRegistration: (bool) ($validated['has_sanitary_registration'] ?? false),
            hasConcentration:        (bool) ($validated['has_concentration'] ?? false),
            hasRiskLevel:            (bool) ($validated['has_risk_level'] ?? false),
            hasPharmaFields:         (bool) ($validated['has_pharma_fields'] ?? false),
            hasDeviceFields:         (bool) ($validated['has_device_fields'] ?? false),
            hasLabBrand:             (bool) ($validated['has_lab_brand'] ?? false),
        );
    }
}
