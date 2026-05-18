<?php

use App\Modules\Catalog\Infrastructure\Http\Controllers\CategoryController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ImportController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductKitComponentController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductPresentationController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\SupplierController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\UnitOfMeasureController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:products.view')->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:products.create')->only(['store']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:products.update')->only(['update']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:products.delete')->only(['destroy']);

    Route::get('categories-tree', [CategoryController::class, 'tree'])
        ->middleware('permission:products.view');

    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:products.view')->only(['index', 'show']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:products.create')->only(['store']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:products.update')->only(['update']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:products.delete')->only(['destroy']);

    Route::apiResource('products', ProductController::class)
        ->middleware('permission:products.view')->only(['index', 'show']);
    Route::apiResource('products', ProductController::class)
        ->middleware('permission:products.create')->only(['store']);
    Route::apiResource('products', ProductController::class)
        ->middleware('permission:products.update')->only(['update']);
    Route::apiResource('products', ProductController::class)
        ->middleware('permission:products.delete')->only(['destroy']);

    Route::get('products/{product}/kit-availability', [ProductController::class, 'kitAvailability'])
        ->middleware('permission:stock.view');

    Route::get('products/{productId}/presentations', [ProductPresentationController::class, 'index'])
        ->middleware('permission:products.view');
    Route::get('products/{productId}/presentations/tree', [ProductPresentationController::class, 'tree'])
        ->middleware('permission:products.view');
    Route::post('products/{productId}/presentations', [ProductPresentationController::class, 'store'])
        ->middleware('permission:products.update');
    Route::put('presentations/{presentation}', [ProductPresentationController::class, 'update'])
        ->middleware('permission:products.update');
    Route::delete('presentations/{presentation}', [ProductPresentationController::class, 'destroy'])
        ->middleware('permission:products.delete');
    Route::post('presentations/validate-hierarchy', [ProductPresentationController::class, 'validateHierarchy'])
        ->middleware('permission:products.view');
    Route::post('presentations/convert-to-base', [ProductPresentationController::class, 'convertToBase'])
        ->middleware('permission:products.view');

    Route::get('products/{productId}/kit-components', [ProductKitComponentController::class, 'index'])
        ->middleware('permission:products.view');
    Route::put('products/{productId}/kit-components', [ProductKitComponentController::class, 'sync'])
        ->middleware('permission:products.update');
    Route::post('products/{productId}/kit-components/explode', [ProductKitComponentController::class, 'explode'])
        ->middleware('permission:products.view');

    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:suppliers.view')->only(['index', 'show']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:suppliers.create')->only(['store']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:suppliers.update')->only(['update']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:suppliers.delete')->only(['destroy']);

    Route::post('import/products', [ImportController::class, 'importProducts'])
        ->middleware('permission:products.import');
    Route::post('import/suppliers', [ImportController::class, 'importSuppliers'])
        ->middleware('permission:suppliers.import');
    Route::get('import/templates/{entity}', [ImportController::class, 'downloadTemplate'])
        ->middleware('permission:products.view');
});
