<?php

use App\Modules\Integration\Infrastructure\Http\Controllers\ConsumptionController;
use App\Modules\Integration\Infrastructure\Http\Controllers\IntegrationController;
use App\Modules\Integration\Infrastructure\Http\Controllers\SchedulingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('integrations', [IntegrationController::class, 'index'])
        ->middleware('permission:integraciones.ver');
    Route::post('integrations', [IntegrationController::class, 'store'])
        ->middleware('permission:integraciones.configurar');
    Route::get('integrations/{id}', [IntegrationController::class, 'show'])
        ->middleware('permission:integraciones.ver');
    Route::put('integrations/{id}', [IntegrationController::class, 'update'])
        ->middleware('permission:integraciones.configurar');
    Route::delete('integrations/{id}', [IntegrationController::class, 'destroy'])
        ->middleware('permission:integraciones.configurar');
    Route::post('integrations/{id}/test', [IntegrationController::class, 'testConnection'])
        ->middleware('permission:integraciones.configurar');

    Route::get('scheduling/appointments', [SchedulingController::class, 'appointments'])
        ->middleware('permission:integraciones.ver');
    Route::get('scheduling/demand-analysis', [SchedulingController::class, 'demandAnalysis'])
        ->middleware('permission:integraciones.ver');

    Route::get('consumptions', [ConsumptionController::class, 'index'])
        ->middleware('permission:consumos.ver');
    Route::post('consumptions', [ConsumptionController::class, 'store'])
        ->middleware('permission:consumos.crear');
    Route::get('consumptions/{id}', [ConsumptionController::class, 'show'])
        ->middleware('permission:consumos.ver');
    Route::post('consumptions/{id}/retry-sync', [ConsumptionController::class, 'retrySync'])
        ->middleware('permission:consumos.crear');
});
