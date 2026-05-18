<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Repositories;

use App\Modules\Auth\Domain\Entities\User;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function findAll(array $filters = []): array;

    public function save(User $user, ?string $password = null): User;

    public function delete(int $id): void;

    public function assignRoles(int $userId, array $roleNames): void;
}
