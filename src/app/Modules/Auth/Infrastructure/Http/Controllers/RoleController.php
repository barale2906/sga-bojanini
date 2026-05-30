<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use App\Modules\Auth\Infrastructure\Http\Requests\StoreRoleRequest;
use App\Modules\Auth\Infrastructure\Http\Requests\UpdateRoleRequest;
use App\Modules\Auth\Infrastructure\Http\Resources\RoleResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        // Nota: NO usamos withCount('users') del modelo Spatie porque en Laravel 13
        // la instancia vacía del modelo no tiene guard_name, getModelForGuard devuelve
        // null y Eloquent lanza "Class name must be a valid object or a string".
        // Solución: subconsulta directa sobre la tabla pivote model_has_roles.
        $roles = Role::with('permissions')
            ->addSelect([
                'roles.*',
                'users_count' => DB::table('model_has_roles')
                    ->selectRaw('count(*)')
                    ->whereColumn('role_id', 'roles.id'),
            ])
            ->get();

        return $this->success(RoleResource::collection($roles), 'Listado de roles');
    }

    public function show(int $id): JsonResponse
    {
        $role = Role::with('permissions')
            ->addSelect([
                'roles.*',
                'users_count' => DB::table('model_has_roles')
                    ->selectRaw('count(*)')
                    ->whereColumn('role_id', 'roles.id'),
            ])
            ->find($id);

        if (! $role) {
            return $this->error('Rol no encontrado', 404);
        }

        return $this->success(new RoleResource($role), 'Detalle del rol');
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);

        if ($request->has('permission_ids')) {
            $permissionNames = Permission::whereIn('id', $request->validated('permission_ids'))
                ->pluck('name')->toArray();
            $role->syncPermissions($permissionNames);
        }

        $role->load('permissions');

        return $this->created(new RoleResource($role), 'Rol creado exitosamente');
    }

    public function update(int $id, UpdateRoleRequest $request): JsonResponse
    {
        $role = Role::findOrFail($id);

        $role->update(['name' => $request->validated('name')]);

        if ($request->has('permission_ids')) {
            $permissionNames = Permission::whereIn('id', $request->validated('permission_ids'))
                ->pluck('name')->toArray();
            $role->syncPermissions($permissionNames);
        }

        $role->load('permissions');

        return $this->success(new RoleResource($role), 'Rol actualizado exitosamente');
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if ($role->users()->count() > 0) {
            return $this->error('No se puede eliminar un rol que tiene usuarios asignados.', 409);
        }

        $role->delete();

        return $this->noContent('Rol eliminado');
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return $this->success($permissions, 'Listado de permisos');
    }
}
