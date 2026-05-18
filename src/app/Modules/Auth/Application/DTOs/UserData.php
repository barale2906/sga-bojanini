<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTOs;

class UserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $password = null,
        public readonly ?string $phone = null,
        public readonly bool $isActive = true,
        public readonly array $roleIds = [],
    ) {}
}
