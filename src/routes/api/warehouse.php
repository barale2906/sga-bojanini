<?php

use App\Modules\Warehouse\Infrastructure\Http\Controllers\CapacityController;
use App\Modules\Warehouse\Infrastructure\Http\Controllers\LocationController;
use App\Modules\Warehouse\Infrastructure\Http\Controllers\WarehouseController;
use App\Modules\Warehouse\Infrastructure\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    // ── Almacenes ──────────────────────────────────────────────────────────────
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware('permission:almacenes.ver')->only(['index', 'show']);
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware('permission:almacenes.crear')->only(['store']);
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware('permission:almacenes.editar')->only(['update']);
    Route::apiResource('warehouses', WarehouseController::class)
        ->middleware('permission:almacenes.eliminar')->only(['destroy']);

    Route::get('warehouses/{id}/zones', [WarehouseController::class, 'zones'])
        ->middleware('permission:zonas.ver');
    Route::get('warehouses/{id}/locations', [WarehouseController::class, 'locations'])
        ->middleware('permission:ubicaciones.ver');

    // Capacidad del almacén (jerarquía completa)
    Route::get('warehouses/{id}/capacity', [CapacityController::class, 'warehouse'])
        ->middleware('permission:almacenes.ver');

    // ── Zonas ──────────────────────────────────────────────────────────────────
    Route::apiResource('zones', ZoneController::class)
        ->middleware('permission:zonas.ver')->only(['index', 'show']);
    Route::apiResource('zones', ZoneController::class)
        ->middleware('permission:zonas.crear')->only(['store']);
    Route::apiResource('zones', ZoneController::class)
        ->middleware('permission:zonas.editar')->only(['update']);
    Route::apiResource('zones', ZoneController::class)
        ->middleware('permission:zonas.eliminar')->only(['destroy']);

    // Capacidad de la zona con detalle por ubicaciones
    Route::get('zones/{id}/capacity', [CapacityController::class, 'zone'])
        ->middleware('permission:zonas.ver');

    // ── Ubicaciones ────────────────────────────────────────────────────────────
    Route::apiResource('locations', LocationController::class)
        ->middleware('permission:ubicaciones.ver')->only(['index', 'show']);
    Route::apiResource('locations', LocationController::class)
        ->middleware('permission:ubicaciones.crear')->only(['store']);
    Route::apiResource('locations', LocationController::class)
        ->middleware('permission:ubicaciones.editar')->only(['update']);
    Route::apiResource('locations', LocationController::class)
        ->middleware('permission:ubicaciones.eliminar')->only(['destroy']);

    // Capacidad de una ubicación concreta
    Route::get('locations/{id}/capacity', [CapacityController::class, 'location'])
        ->middleware('permission:ubicaciones.ver');
});
