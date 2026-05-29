<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductClassificationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ProductClassificationSeeder']);

        $admin       = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_listar_clasificaciones(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/product-classifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_crud_clasificacion(): void
    {
        // Crear
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/product-classifications', [
                'code'                      => 'TEST',
                'name'                      => 'Clasificación Test',
                'description'               => 'Para pruebas',
                'has_sanitary_registration' => true,
                'has_concentration'         => false,
                'has_risk_level'            => false,
                'has_pharma_fields'         => false,
                'has_device_fields'         => false,
                'has_lab_brand'             => true,
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $id = $response->json('data.id');

        // Leer
        $this->withHeaders($this->auth())
            ->getJson("/api/v1/product-classifications/{$id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'TEST')
            ->assertJsonPath('data.has_sanitary_registration', true)
            ->assertJsonPath('data.has_lab_brand', true);

        // Actualizar
        $this->withHeaders($this->auth())
            ->putJson("/api/v1/product-classifications/{$id}", [
                'code'        => 'TEST',
                'name'        => 'Clasificación Actualizada',
                'has_lab_brand' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Clasificación Actualizada')
            ->assertJsonPath('data.has_lab_brand', false);

        // Eliminar
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/product-classifications/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_codigo_duplicado_rechazado(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/product-classifications', [
                'code' => 'MED',
                'name' => 'Duplicado',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_clasificacion_no_encontrada(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/product-classifications/9999')
            ->assertNotFound();
    }

    public function test_filtrar_clasificaciones_activas(): void
    {
        ProductClassificationModel::where('code', 'OTR')->update(['is_active' => false]);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/product-classifications?is_active=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_seed_genera_tres_clasificaciones_base(): void
    {
        $this->assertDatabaseHas('product_classifications', ['code' => 'MED', 'has_pharma_fields' => true]);
        $this->assertDatabaseHas('product_classifications', ['code' => 'DM',  'has_device_fields' => true]);
        $this->assertDatabaseHas('product_classifications', ['code' => 'OTR', 'has_sanitary_registration' => false]);
    }
}
