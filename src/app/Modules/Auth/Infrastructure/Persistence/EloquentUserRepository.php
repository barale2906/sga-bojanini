<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Modules\Auth\Domain\Entities\User;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $model = UserModel::with('roles.permissions')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $model = UserModel::with('roles.permissions')->where('email', $email)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = UserModel::with('roles.permissions');

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['role'])) {
            $query->role($filters['role']);
        }

        return $query->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function save(User $user, ?string $password = null): User
    {
        $model = $user->getId()
            ? UserModel::findOrFail($user->getId())
            : new UserModel();

        $model->name = $user->getName();
        $model->email = $user->getEmail();
        $model->phone = $user->getPhone();
        $model->is_active = $user->isActive();

        if ($password !== null) {
            $model->password = $password;
        }

        $model->save();

        if (! empty($user->getRoles())) {
            $model->syncRoles($user->getRoles());
        }

        $model->load('roles.permissions');

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        UserModel::findOrFail($id)->delete();
    }

    public function assignRoles(int $userId, array $roleNames): void
    {
        $model = UserModel::findOrFail($userId);
        $model->syncRoles($roleNames);
    }

    private function toDomain(UserModel $model): User
    {
        return new User(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            phone: $model->phone,
            isActive: (bool) $model->is_active,
            roles: $model->roles->pluck('name')->toArray(),
            permissions: $model->getAllPermissions()->pluck('name')->toArray(),
        );
    }
}
