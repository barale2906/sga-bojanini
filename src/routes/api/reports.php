<?php

use App\Modules\Shared\Infrastructure\Http\Controllers\NotificationController;
use App\Modules\Shared\Infrastructure\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])
        ->middleware('permission:notificaciones.ver');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->middleware('permission:notificaciones.ver');
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->middleware('permission:notificaciones.ver');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->middleware('permission:notificaciones.ver');

    Route::prefix('reports')->middleware('permission:reportes.ver')->group(function () {
        Route::get('inventory', [ReportController::class, 'inventory'])->middleware('audit.access');
        Route::get('movements', [ReportController::class, 'movements'])->middleware('audit.access');
        Route::get('expiring', [ReportController::class, 'expiring'])->middleware('audit.access');
        Route::get('purchases', [ReportController::class, 'purchases'])->middleware('audit.access');
        Route::get('consumption', [ReportController::class, 'consumption'])->middleware('audit.access');
        Route::get('conditions', [ReportController::class, 'conditions'])->middleware('audit.access');
        Route::get('audit', [ReportController::class, 'audit'])->middleware('audit.access');
    });
});
