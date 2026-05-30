<?php

use App\Modules\CostCenter\Infrastructure\Http\Controllers\CostCenterController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\MedicalServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    // ─── Centros de Costo ────────────────────────────────────────────────────────
    Route::apiResource('cost-centers', CostCenterController::class)
        ->middleware('permission:centros_costo.ver')->only(['index', 'show']);
    Route::apiResource('cost-centers', CostCenterController::class)
        ->middleware('permission:centros_costo.crear')->only(['store']);
    Route::apiResource('cost-centers', CostCenterController::class)
        ->middleware('permission:centros_costo.editar')->only(['update']);
    Route::apiResource('cost-centers', CostCenterController::class)
        ->middleware('permission:centros_costo.eliminar')->only(['destroy']);

    // ─── Servicios Médicos ───────────────────────────────────────────────────────
    Route::apiResource('medical-services', MedicalServiceController::class)
        ->middleware('permission:servicios_medicos.ver')->only(['index', 'show']);
    Route::apiResource('medical-services', MedicalServiceController::class)
        ->middleware('permission:servicios_medicos.crear')->only(['store']);
    Route::apiResource('medical-services', MedicalServiceController::class)
        ->middleware('permission:servicios_medicos.editar')->only(['update']);
    Route::apiResource('medical-services', MedicalServiceController::class)
        ->middleware('permission:servicios_medicos.eliminar')->only(['destroy']);
});
