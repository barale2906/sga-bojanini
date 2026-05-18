<?php

use App\Modules\Monitoring\Infrastructure\Http\Controllers\AlertRuleController;
use App\Modules\Monitoring\Infrastructure\Http\Controllers\ConditionReportController;
use App\Modules\Monitoring\Infrastructure\Http\Controllers\SensorController;
use App\Modules\Monitoring\Infrastructure\Http\Controllers\SensorReadingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    Route::apiResource('sensors', SensorController::class)
        ->middleware('permission:sensors.view')->only(['index', 'show']);
    Route::apiResource('sensors', SensorController::class)
        ->middleware('permission:sensors.create')->only(['store']);
    Route::apiResource('sensors', SensorController::class)
        ->middleware('permission:sensors.update')->only(['update']);
    Route::apiResource('sensors', SensorController::class)
        ->middleware('permission:sensors.delete')->only(['destroy']);

    Route::post('sensors/{sensorId}/readings', [SensorReadingController::class, 'store'])
        ->middleware('permission:readings.create');
    Route::get('sensors/{sensorId}/readings', [SensorReadingController::class, 'index'])
        ->middleware('permission:readings.view');
    Route::get('sensors/{sensorId}/statistics', [SensorReadingController::class, 'statistics'])
        ->middleware('permission:readings.view');
    Route::get('sensors/{sensorId}/trend', [SensorReadingController::class, 'trend'])
        ->middleware('permission:readings.view');

    Route::get('sensors/{sensorId}/alert-rules', [AlertRuleController::class, 'index'])
        ->middleware('permission:alert_rules.view');
    Route::post('sensors/{sensorId}/alert-rules', [AlertRuleController::class, 'store'])
        ->middleware('permission:alert_rules.create');
    Route::put('alert-rules/{id}', [AlertRuleController::class, 'update'])
        ->middleware('permission:alert_rules.update');
    Route::delete('alert-rules/{id}', [AlertRuleController::class, 'destroy'])
        ->middleware('permission:alert_rules.delete');

    Route::post('monitoring/reports/generate', [ConditionReportController::class, 'generate'])
        ->middleware('permission:readings.view');
    Route::get('monitoring/reports', [ConditionReportController::class, 'index'])
        ->middleware('permission:readings.view');
});

Route::middleware('iot.token')->prefix('v1')->group(function () {
    Route::post('sensors/readings/bulk', [SensorReadingController::class, 'storeBulk']);
});
