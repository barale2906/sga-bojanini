<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;

class LogoutUseCase
{
    public function execute(UserModel $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
