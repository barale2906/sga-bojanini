<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Controllers;

use App\Modules\Auth\Application\DTOs\UserData;
use App\Modules\Auth\Application\UseCases\CreateUserUseCase;
use App\Modules\Auth\Application\UseCases\UpdateUserUseCase;
use App\Modules\Auth\Infrastructure\Http\Requests\StoreUserRequest;
use App\Modules\Auth\Infrastructure\Http\Requests\UpdateUserRequest;
use App\Modules\Auth\Infrastructure\Http\Resources\UserResource;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = UserModel::with('roles.permissions');

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('role')) {
            $query->role($request->query('role'));
        }

        $perPage = min(
            $request->integer('per_page', config('sga.pagination.default_per_page')),
            config('sga.pagination.max_per_page')
        );

        $users = $query->paginate($perPage);
        $users->through(fn ($user) => (new UserResource($user))->resolve());

        return $this->paginated($users, 'Listado de usuarios');
    }

    public function store(StoreUserRequest $request, CreateUserUseCase $useCase): JsonResponse
    {
        $data = new UserData(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            phone: $request->validated('phone'),
            isActive: $request->boolean('is_active', true),
            roleIds: $request->validated('role_ids', []),
        );

        $user = $useCase->execute($data);

        return $this->created(new UserResource($user), 'Usuario creado exitosamente');
    }

    public function show(int $id): JsonResponse
    {
        $user = UserModel::with('roles.permissions')->find($id);

        if (! $user) {
            return $this->error('Usuario no encontrado', 404);
        }

        return $this->success(new UserResource($user), 'Detalle del usuario');
    }

    public function update(int $id, UpdateUserRequest $request, UpdateUserUseCase $useCase): JsonResponse
    {
        $data = new UserData(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            phone: $request->validated('phone'),
            isActive: $request->boolean('is_active', true),
            roleIds: $request->validated('role_ids', []),
        );

        $user = $useCase->execute($id, $data);

        return $this->success(new UserResource($user), 'Usuario actualizado exitosamente');
    }

    public function destroy(int $id): JsonResponse
    {
        $user = UserModel::find($id);

        if (! $user) {
            return $this->error('Usuario no encontrado', 404);
        }

        $user->delete();

        return $this->noContent('Usuario eliminado');
    }

    public function assignRoles(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'role_ids'   => ['required', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $user = UserModel::findOrFail($id);
        $roleNames = Role::whereIn('id', $request->input('role_ids'))->pluck('name')->toArray();
        $user->syncRoles($roleNames);
        $user->load('roles.permissions');

        return $this->success(new UserResource($user), 'Roles asignados exitosamente');
    }
}
