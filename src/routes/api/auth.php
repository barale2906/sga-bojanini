<?php

use App\Modules\Auth\Infrastructure\Http\Controllers\AuthController;
use App\Modules\Auth\Infrastructure\Http\Controllers\RoleController;
use App\Modules\Auth\Infrastructure\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'user.is_active'])->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/password', [AuthController::class, 'changePassword']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);

        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create');
        Route::get('users/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::put('users/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
        Route::delete('users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
        Route::post('users/{id}/roles', [UserController::class, 'assignRoles'])->middleware('permission:roles.update');

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::get('roles/{id}', [RoleController::class, 'show'])->middleware('permission:roles.view');
        Route::put('roles/{id}', [RoleController::class, 'update'])->middleware('permission:roles.update');
        Route::delete('roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
    });
});
