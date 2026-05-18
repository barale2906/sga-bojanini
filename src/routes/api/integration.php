<?php

use App\Modules\Integration\Infrastructure\Http\Controllers\ConsumptionController;
use App\Modules\Integration\Infrastructure\Http\Controllers\IntegrationController;
use App\Modules\Integration\Infrastructure\Http\Controllers\SchedulingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('integrations', [IntegrationController::class, 'index'])
        ->middleware('permission:integrations.view');
    Route::post('integrations', [IntegrationController::class, 'store'])
        ->middleware('permission:integrations.configure');
    Route::get('integrations/{id}', [IntegrationController::class, 'show'])
        ->middleware('permission:integrations.view');
    Route::put('integrations/{id}', [IntegrationController::class, 'update'])
        ->middleware('permission:integrations.configure');
    Route::delete('integrations/{id}', [IntegrationController::class, 'destroy'])
        ->middleware('permission:integrations.configure');
    Route::post('integrations/{id}/test', [IntegrationController::class, 'testConnection'])
        ->middleware('permission:integrations.configure');

    Route::get('scheduling/appointments', [SchedulingController::class, 'appointments'])
        ->middleware('permission:integrations.view');
    Route::get('scheduling/demand-analysis', [SchedulingController::class, 'demandAnalysis'])
        ->middleware('permission:integrations.view');

    Route::get('consumptions', [ConsumptionController::class, 'index'])
        ->middleware('permission:consumptions.view');
    Route::post('consumptions', [ConsumptionController::class, 'store'])
        ->middleware('permission:consumptions.create');
    Route::get('consumptions/{id}', [ConsumptionController::class, 'show'])
        ->middleware('permission:consumptions.view');
    Route::post('consumptions/{id}/retry-sync', [ConsumptionController::class, 'retrySync'])
        ->middleware('permission:consumptions.create');
});
