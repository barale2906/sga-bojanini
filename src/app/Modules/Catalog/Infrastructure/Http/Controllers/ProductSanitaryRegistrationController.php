<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\ProductSanitaryRegistrationData;
use App\Modules\Catalog\Application\UseCases\CreateProductSanitaryRegistrationUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteProductSanitaryRegistrationUseCase;
use App\Modules\Catalog\Application\UseCases\ListProductSanitaryRegistrationsUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateProductSanitaryRegistrationUseCase;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreProductSanitaryRegistrationRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateProductSanitaryRegistrationRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\ProductSanitaryRegistrationResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductSanitaryRegistrationController extends Controller
{
    use ApiResponse;

    public function index(int $variantId, Request $request, ListProductSanitaryRegistrationsUseCase $useCase): JsonResponse
    {
        $onlyActive    = filter_var($request->query('only_active', false), FILTER_VALIDATE_BOOLEAN);
        $registrations = $useCase->execute($variantId, $onlyActive);

        return $this->success(
            ProductSanitaryRegistrationResource::collection($registrations),
            'Registros sanitarios de la variante'
        );
    }

    public function store(int $variantId, StoreProductSanitaryRegistrationRequest $request, CreateProductSanitaryRegistrationUseCase $useCase): JsonResponse
    {
        $validated    = $request->validated();
        $registration = $useCase->execute(new ProductSanitaryRegistrationData(
            productVariantId:   $variantId,
            registrationNumber: $validated['registration_number'],
            expiryDate:         new DateTimeImmutable($validated['expiry_date']),
            isActive:           (bool) ($validated['is_active'] ?? true),
        ));

        return $this->created(
            new ProductSanitaryRegistrationResource($registration),
            'Registro sanitario creado exitosamente'
        );
    }

    public function update(int $variantId, int $registration, UpdateProductSanitaryRegistrationRequest $request, UpdateProductSanitaryRegistrationUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();
        $entity    = $useCase->execute($registration, new ProductSanitaryRegistrationData(
            productVariantId:   $variantId,
            registrationNumber: $validated['registration_number'],
            expiryDate:         new DateTimeImmutable($validated['expiry_date']),
            isActive:           (bool) ($validated['is_active'] ?? true),
        ));

        return $this->success(
            new ProductSanitaryRegistrationResource($entity),
            'Registro sanitario actualizado exitosamente'
        );
    }

    public function destroy(int $variantId, int $registration, DeleteProductSanitaryRegistrationUseCase $useCase): JsonResponse
    {
        $useCase->execute($registration);

        return $this->noContent('Registro sanitario eliminado');
    }
}
