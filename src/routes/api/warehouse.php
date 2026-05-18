<?php

use App\Modules\Warehouse\Infrastructure\Http\Controllers\LocationController;
use App\Modules\Warehouse\Infrastructure\Http\Controllers\WarehouseController;
use App\Modules\Warehouse\Infrastructure\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware('permission:warehouses.view')->only(['index', 'show']);
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware('permission:warehouses.create')->only(['store']);
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware('permission:warehouses.update')->only(['update']);
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware('permission:warehouses.delete')->only(['destroy']);

    Route::get('warehouses/{id}/zones', [WarehouseController::class, 'zones'])
        ->middleware('permission:zones.view');
    Route::get('warehouses/{id}/locations', [WarehouseController::class, 'locations'])
        ->middleware('permission:locations.view');

    Route::apiResource('zones', ZoneController::class)
        ->middleware('permission:zones.view')->only(['index', 'show']);
    Route::apiResource('zones', ZoneController::class)
        ->middleware('permission:zones.create')->only(['store']);
    Route::apiResource('zones', ZoneController::class)
        ->middleware('permission:zones.update')->only(['update']);
    Route::apiResource('zones', ZoneController::class)
        ->middleware('permission:zones.delete')->only(['destroy']);

    Route::apiResource('locations', LocationController::class)
        ->middleware('permission:locations.view')->only(['index', 'show']);
    Route::apiResource('locations', LocationController::class)
        ->middleware('permission:locations.create')->only(['store']);
    Route::apiResource('locations', LocationController::class)
        ->middleware('permission:locations.update')->only(['update']);
    Route::apiResource('locations', LocationController::class)
        ->middleware('permission:locations.delete')->only(['destroy']);
});
