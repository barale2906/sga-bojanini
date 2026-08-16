<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\ProductVariantData;
use App\Modules\Catalog\Application\UseCases\CreateProductVariantUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteProductVariantUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateProductVariantUseCase;
use App\Modules\Catalog\Domain\Repositories\ProductVariantRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreProductVariantRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateProductVariantRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\ProductVariantResource;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ProductVariantController extends Controller
{
    use ApiResponse;

    public function index(int $genericId): JsonResponse
    {
        $variants = ProductVariantModel::where('generic_product_id', $genericId)
            ->with('sanitaryRegistrations')
            ->orderBy('lab_brand')
            ->get();

        return $this->success(
            ProductVariantResource::collection($variants),
            'Variantes del producto genérico'
        );
    }

    public function store(int $genericId, StoreProductVariantRequest $request, CreateProductVariantUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();
        $variant   = $useCase->execute(new ProductVariantData(
            genericProductId:       $genericId,
            labBrand:               $validated['lab_brand'],
            brandSku:               $validated['brand_sku'] ?? null,
            commercialPresentation: $validated['commercial_presentation'] ?? null,
            serieReference:         $validated['serie_reference'] ?? null,
            usefulLife:             $validated['useful_life'] ?? null,
            riskLevel:              $validated['risk_level'] ?? null,
        ));

        return $this->created(
            new ProductVariantResource($variant),
            'Variante creada exitosamente'
        );
    }

    public function show(int $genericId, int $variantId, ProductVariantRepositoryInterface $repository): JsonResponse
    {
        $variant = $repository->findById($variantId);

        if ($variant === null || $variant->getGenericProductId() !== $genericId) {
            return $this->error('Variante no encontrada', 404);
        }

        return $this->success(
            new ProductVariantResource($variant),
            'Detalle de la variante'
        );
    }

    public function update(int $genericId, int $variantId, UpdateProductVariantRequest $request, UpdateProductVariantUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();
        $entity    = $useCase->execute($variantId, new ProductVariantData(
            genericProductId:       $genericId,
            labBrand:               $validated['lab_brand'],
            brandSku:               $validated['brand_sku'] ?? null,
            commercialPresentation: $validated['commercial_presentation'] ?? null,
            serieReference:         $validated['serie_reference'] ?? null,
            usefulLife:             $validated['useful_life'] ?? null,
            riskLevel:              $validated['risk_level'] ?? null,
        ));

        return $this->success(
            new ProductVariantResource($entity),
            'Variante actualizada exitosamente'
        );
    }

    public function destroy(int $genericId, int $variantId, DeleteProductVariantUseCase $useCase): JsonResponse
    {
        $useCase->execute($variantId);

        return $this->noContent('Variante eliminada');
    }
}
