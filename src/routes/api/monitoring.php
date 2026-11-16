<?php

use App\Modules\Monitoring\Infrastructure\Http\Controllers\AlertRuleController;
use App\Modules\Monitoring\Infrastructure\Http\Controllers\CalibrationCertificateController;
use App\Modules\Monitoring\Infrastructure\Http\Controllers\ConditionReportController;
use App\Modules\Monitoring\Infrastructure\Http\Controllers\SensorController;
use App\Modules\Monitoring\Infrastructure\Http\Controllers\SensorReadingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    Route::apiResource('sensors', SensorController::class)
        ->middleware('permission:sensores.ver')->only(['index', 'show']);
    Route::apiResource('sensors', SensorController::class)
        ->middleware('permission:sensores.crear')->only(['store']);
    Route::apiResource('sensors', SensorController::class)
        ->middleware('permission:sensores.editar')->only(['update']);
    Route::apiResource('sensors', SensorController::class)
        ->middleware('permission:sensores.eliminar')->only(['destroy']);

    Route::get('sensors/{id}/users', [SensorController::class, 'users'])
        ->middleware('permission:sensores.asignar');

    // Certificados de calibración (historial: el anterior se conserva al cargar uno nuevo).
    Route::get('sensors/{sensorId}/calibrations', [CalibrationCertificateController::class, 'index'])
        ->middleware('permission:sensores.ver');
    Route::post('sensors/{sensorId}/calibrations', [CalibrationCertificateController::class, 'store'])
        ->middleware('permission:sensores.editar');
    Route::get('calibrations/{id}/download', [CalibrationCertificateController::class, 'download'])
        ->middleware('permission:sensores.ver');
    Route::delete('calibrations/{id}', [CalibrationCertificateController::class, 'destroy'])
        ->middleware('permission:sensores.editar');

    Route::get('sensors/{sensorId}/readings/latest', [SensorReadingController::class, 'latest'])
        ->middleware('permission:lecturas.ver');
    Route::get('sensors/{sensorId}/readings/export', [SensorReadingController::class, 'export'])
        ->middleware('permission:lecturas.ver');
    Route::post('sensors/{sensorId}/readings', [SensorReadingController::class, 'store'])
        ->middleware('permission:lecturas.crear');
    Route::get('sensors/{sensorId}/readings', [SensorReadingController::class, 'index'])
        ->middleware('permission:lecturas.ver');
    Route::get('sensors/{sensorId}/statistics', [SensorReadingController::class, 'statistics'])
        ->middleware('permission:lecturas.ver');
    Route::get('sensors/{sensorId}/trend', [SensorReadingController::class, 'trend'])
        ->middleware('permission:lecturas.ver');

    Route::get('sensors/{sensorId}/alert-rules', [AlertRuleController::class, 'index'])
        ->middleware('permission:reglas_alerta.ver');
    Route::post('sensors/{sensorId}/alert-rules', [AlertRuleController::class, 'store'])
        ->middleware('permission:reglas_alerta.crear');
    Route::put('alert-rules/{id}', [AlertRuleController::class, 'update'])
        ->middleware('permission:reglas_alerta.editar');
    Route::delete('alert-rules/{id}', [AlertRuleController::class, 'destroy'])
        ->middleware('permission:reglas_alerta.eliminar');

    Route::post('monitoring/reports/generate', [ConditionReportController::class, 'generate'])
        ->middleware('permission:lecturas.ver');
    Route::get('monitoring/reports', [ConditionReportController::class, 'index'])
        ->middleware('permission:lecturas.ver');
});

Route::middleware('iot.token')->prefix('v1')->group(function () {
    Route::post('sensors/readings/bulk', [SensorReadingController::class, 'storeBulk']);
});
