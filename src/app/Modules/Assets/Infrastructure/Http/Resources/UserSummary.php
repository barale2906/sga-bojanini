<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;

/** Representación mínima de un usuario relacionado (responsable, técnico, autor). */
final class UserSummary
{
    /** @return array{id: int, name: string}|null */
    public static function of(?UserModel $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}
