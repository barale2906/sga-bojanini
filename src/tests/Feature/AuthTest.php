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

        $response->assertStatus(401)
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

    public function test_refresh_mantiene_vigente_el_token_anterior_durante_la_gracia(): void
    {
        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $oldToken = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/v1/auth/refresh');
        $response->assertStatus(200)->assertJsonStructure(['data' => ['token']]);
        $newToken = $response->json('data.token');

        // Petición en vuelo / otra pestaña con el token anterior: no debe dar 401
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);

        // Pasada la gracia, el token anterior deja de servir y el nuevo sigue vigente
        $this->travel(3)->minutes();

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_refresh_repetido_no_extiende_la_vigencia_del_token_anterior(): void
    {
        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $oldToken = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(200);
        $firstExpiry = $admin->tokens()->where('name', 'test')->first()->expires_at;

        // Dos pestañas renovando con el mismo token: la segunda no le alarga la vida
        $this->travel(1)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(200);

        $this->assertEquals(
            $firstExpiry->toDateTimeString(),
            $admin->tokens()->where('name', 'test')->first()->expires_at->toDateTimeString()
        );
    }
}
