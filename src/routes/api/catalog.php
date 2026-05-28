<?php

use App\Modules\Catalog\Infrastructure\Http\Controllers\CategoryController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ImportController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductKitComponentController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductPresentationController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\SupplierController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\SupplierProductController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\UnitOfMeasureController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:productos.ver')->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:productos.crear')->only(['store']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:productos.editar')->only(['update']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:productos.eliminar')->only(['destroy']);

    Route::get('categories-tree', [CategoryController::class, 'tree'])
        ->middleware('permission:productos.ver');

    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:productos.ver')->only(['index', 'show']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:productos.crear')->only(['store']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:productos.editar')->only(['update']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:productos.eliminar')->only(['destroy']);

    Route::apiResource('products', ProductController::class)
        ->middleware('permission:productos.ver')->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)
        ->middleware('permission:productos.crear')->only(['store']);
    Route::apiResource('products', ProductController::class)
        ->middleware('permission:productos.editar')->only(['update']);
    Route::apiResource('products', ProductController::class)
        ->middleware('permission:productos.eliminar')->only(['destroy']);

    Route::get('products/{product}/kit-availability', [ProductController::class, 'kitAvailability'])
        ->middleware('permission:stock.ver');

    // Presentaciones independientes (catálogo)
    Route::get('presentations', [ProductPresentationController::class, 'index'])
        ->middleware('permission:productos.ver');
    Route::get('presentations/tree', [ProductPresentationController::class, 'tree'])
        ->middleware('permission:productos.ver');
    Route::post('presentations', [ProductPresentationController::class, 'store'])
        ->middleware('permission:productos.crear');
    Route::put('presentations/{presentation}', [ProductPresentationController::class, 'update'])
        ->middleware('permission:productos.editar');
    Route::delete('presentations/{presentation}', [ProductPresentationController::class, 'destroy'])
        ->middleware('permission:productos.eliminar');
    Route::post('presentations/validate-hierarchy', [ProductPresentationController::class, 'validateHierarchy'])
        ->middleware('permission:productos.ver');
    Route::post('presentations/convert-to-base', [ProductPresentationController::class, 'convertToBase'])
        ->middleware('permission:productos.ver');

    // Presentaciones por producto (relación M:N)
    Route::get('products/{productId}/presentations', [ProductPresentationController::class, 'indexByProduct'])
        ->middleware('permission:productos.ver');
    Route::get('products/{productId}/presentations/tree', [ProductPresentationController::class, 'treeByProduct'])
        ->middleware('permission:productos.ver');
    Route::post('products/{productId}/presentations/{presentationId}', [ProductPresentationController::class, 'attach'])
        ->middleware('permission:productos.editar');
    Route::delete('products/{productId}/presentations/{presentationId}', [ProductPresentationController::class, 'detach'])
        ->middleware('permission:productos.editar');

    Route::get('products/{productId}/kit-components', [ProductKitComponentController::class, 'index'])
        ->middleware('permission:productos.ver');
    Route::put('products/{productId}/kit-components', [ProductKitComponentController::class, 'sync'])
        ->middleware('permission:productos.editar');
    Route::delete('products/{productId}/kit-components/{componentId}', [ProductKitComponentController::class, 'destroy'])
        ->middleware('permission:productos.editar');
    Route::post('products/{productId}/kit-components/explode', [ProductKitComponentController::class, 'explode'])
        ->middleware('permission:productos.ver');

    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:proveedores.ver')->only(['index', 'show']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:proveedores.crear')->only(['store']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:proveedores.editar')->only(['update']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:proveedores.eliminar')->only(['destroy']);

    // Productos del proveedor
    Route::prefix('suppliers/{supplierId}/products')->group(function () {
        Route::get('/', [SupplierProductController::class, 'index'])
            ->middleware('permission:proveedores.ver');

        // Rutas "by-category" ANTES de {productId} para evitar conflictos de routing
        Route::post('by-category', [SupplierProductController::class, 'attachByCategory'])
            ->middleware('permission:proveedores.editar');
        Route::delete('by-category', [SupplierProductController::class, 'detachByCategory'])
            ->middleware('permission:proveedores.editar');

        Route::post('{productId}', [SupplierProductController::class, 'attach'])
            ->middleware('permission:proveedores.editar');
        Route::put('{productId}', [SupplierProductController::class, 'update'])
            ->middleware('permission:proveedores.editar');
        Route::delete('{productId}', [SupplierProductController::class, 'detach'])
            ->middleware('permission:proveedores.editar');
    });

    Route::post('import/products', [ImportController::class, 'importProducts'])
        ->middleware('permission:productos.importar');
    Route::post('import/suppliers', [ImportController::class, 'importSuppliers'])
        ->middleware('permission:proveedores.importar');
    Route::get('import/templates/{entity}', [ImportController::class, 'downloadTemplate'])
        ->middleware('permission:productos.ver');
});
