<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entities;

/**
 * Entidad que representa un usuario del sistema.
 */
class User
{
    public function __construct(
        private ?int $id,
        private string $name,
        private string $email,
        private ?string $phone = null,
        private bool $isActive = true,
        private array $roles = [],
        private array $permissions = [],
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
