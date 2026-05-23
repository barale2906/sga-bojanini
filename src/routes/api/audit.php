<?php

use App\Modules\Audit\Infrastructure\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('audit-logs/summary', [AuditLogController::class, 'summary'])
        ->middleware('permission:auditoria.ver');
    Route::get('audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('permission:auditoria.exportar');
    Route::get('audit-logs/{id}', [AuditLogController::class, 'show'])
        ->middleware('permission:auditoria.ver');
    Route::get('audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:auditoria.ver');
});
