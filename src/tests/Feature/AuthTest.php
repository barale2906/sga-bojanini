<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    public function test_login_exitoso(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'alexanderbarajas@gmail.com',
            'password' => '10203040',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'user'],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_login_credenciales_invalidas(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'alexanderbarajas@gmail.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(409)
            ->assertJson(['success' => false]);
    }

    public function test_acceso_sin_token_devuelve_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }

    public function test_crud_usuarios(): void
    {
        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name'     => 'Test User',
                'email'    => 'test@sga.bojanini.com',
                'password' => 'Test1234!',
            ]);
        $response->assertStatus(201);
        $userId = $response->json('data.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/users');
        $response->assertStatus(200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/users/{$userId}", [
                'name'  => 'Test Updated',
                'email' => 'test@sga.bojanini.com',
            ]);
        $response->assertStatus(200);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/users/{$userId}");
        $response->assertStatus(200);
    }
}
