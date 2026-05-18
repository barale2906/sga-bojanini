<?php

namespace Tests;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function authenticateAsRole(string $role): UserModel
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $user = UserModel::create([
            'name'      => "Usuario {$role}",
            'email'     => "{$role}@test.sga.local",
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    protected function bearerTokenFor(UserModel $user): string
    {
        return $user->createToken('test', $user->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    protected function bearerAuthFor(UserModel $user): array
    {
        return ['Authorization' => 'Bearer '.$this->bearerTokenFor($user)];
    }
}
