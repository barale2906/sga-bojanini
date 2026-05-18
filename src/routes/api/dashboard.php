<?php

use App\Modules\Shared\Infrastructure\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active', 'permission:dashboard.view'])->prefix('v1/dashboard')->group(function () {
    Route::get('inventory', [DashboardController::class, 'inventory']);
    Route::get('purchasing', [DashboardController::class, 'purchasing']);
    Route::get('monitoring', [DashboardController::class, 'monitoring']);
    Route::get('activity', [DashboardController::class, 'activity']);
    Route::get('alerts', [DashboardController::class, 'alerts']);
});
