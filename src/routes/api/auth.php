<?php

use App\Modules\Auth\Infrastructure\Http\Controllers\AuthController;
use App\Modules\Auth\Infrastructure\Http\Controllers\RoleController;
use App\Modules\Auth\Infrastructure\Http\Controllers\UserController;
use App\Modules\Shared\Infrastructure\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'user.is_active'])->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('auth/menu', [MenuController::class, 'index']);
        Route::put('auth/password', [AuthController::class, 'changePassword']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);

        Route::get('users', [UserController::class, 'index'])->middleware('permission:usuarios.ver');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:usuarios.crear');
        Route::get('users/{id}', [UserController::class, 'show'])->middleware('permission:usuarios.ver');
        Route::put('users/{id}', [UserController::class, 'update'])->middleware('permission:usuarios.editar');
        Route::delete('users/{id}', [UserController::class, 'destroy'])->middleware('permission:usuarios.eliminar');
        Route::post('users/{id}/roles', [UserController::class, 'assignRoles'])->middleware('permission:roles.editar');
        Route::get('users/{id}/warehouses', [UserController::class, 'warehouses'])->middleware('permission:almacenes.asignar');
        Route::put('users/{id}/warehouses', [UserController::class, 'assignWarehouses'])->middleware('permission:almacenes.asignar');
        Route::get('users/{id}/sensors', [UserController::class, 'sensors'])->middleware('permission:sensores.asignar');
        Route::put('users/{id}/sensors', [UserController::class, 'assignSensors'])->middleware('permission:sensores.asignar');

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.ver');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.crear');
        Route::get('roles/{id}', [RoleController::class, 'show'])->middleware('permission:roles.ver');
        Route::put('roles/{id}', [RoleController::class, 'update'])->middleware('permission:roles.editar');
        Route::delete('roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.eliminar');

        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.ver');
    });
});
