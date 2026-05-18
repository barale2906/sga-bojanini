<?php

use App\Modules\Audit\Infrastructure\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('audit-logs/summary', [AuditLogController::class, 'summary'])
        ->middleware('permission:audit.view');
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('permission:audit.export');
    Route::get('audit-logs/{id}', [AuditLogController::class, 'show'])
        ->middleware('permission:audit.view');
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view');
});
