<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin       = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── Listado ──────────────────────────────────────────────────────────────

    public function test_listar_servicios_medicos_del_seeder(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(12, 'data'); // 12 servicios del seeder
    }

    public function test_filtrar_servicios_activos(): void
    {
        MedicalServiceModel::where('code', 'UCI')->update(['is_active' => false]);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services?is_active=1')
            ->assertOk()
            ->assertJsonCount(11, 'data');
    }

    public function test_buscar_servicio_por_nombre(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services?search=Cirug')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services?search=Cirug')
            ->json('data');

        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('Cirug', $item['name']);
        }
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function test_crud_servicio_medico(): void
    {
        // Crear
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/medical-services', [
                'code'        => 'TEST-SVC',
                'name'        => 'Servicio de Prueba',
                'description' => 'Descripción de prueba',
                'is_active'   => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'TEST-SVC')
            ->assertJsonPath('data.name', 'Servicio de Prueba')
            ->assertJsonPath('data.is_active', true);

        $id = $response->json('data.id');

        // Leer
        $this->withHeaders($this->auth())
            ->getJson("/api/v1/medical-services/{$id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'TEST-SVC');

        // Actualizar
        $this->withHeaders($this->auth())
            ->putJson("/api/v1/medical-services/{$id}", [
                'code'      => 'TEST-SVC',
                'name'      => 'Servicio Actualizado',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Servicio Actualizado')
            ->assertJsonPath('data.is_active', false);

        // Eliminar
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/medical-services/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('medical_services', ['id' => $id]);
    }

    public function test_codigo_duplicado_rechazado_por_validacion(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/medical-services', [
                'code' => 'UCI', // ya existe en el seeder
                'name' => 'Duplicado',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_codigo_normalizado_a_mayusculas(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/medical-services', [
                'code' => 'new-svc',
                'name' => 'Servicio Minúsculas',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'NEW-SVC');
    }

    public function test_servicio_no_encontrado_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/99999')
            ->assertNotFound();
    }

    public function test_campos_requeridos_rechazados(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/medical-services', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name']);
    }

    // ─── Protección de integridad ─────────────────────────────────────────────

    public function test_no_eliminar_servicio_con_movimientos_asociados(): void
    {
        $service  = MedicalServiceModel::first();
        $center   = CostCenterModel::where('type', 'external')->first();
        $product  = \App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel::first();
        $user     = UserModel::first();
        $wh       = WarehouseModel::create(['name' => 'WH-SVC', 'code' => 'WH-SV', 'is_active' => true]);

        StockMovementModel::create([
            'warehouse_id'   => $wh->id,
            'product_id'     => $product->id,
            'movement_type'  => 'exit',
            'quantity'       => -1,
            'cost_center_id' => $center->id,
            'service_id'     => $service->id,
            'user_id'        => $user->id,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/medical-services/{$service->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ─── Actualización con código que ya pertenece al mismo registro ──────────

    public function test_actualizar_servicio_mantiene_su_propio_codigo(): void
    {
        $service = MedicalServiceModel::where('code', 'UCI')->first();

        $this->withHeaders($this->auth())
            ->putJson("/api/v1/medical-services/{$service->id}", [
                'code' => 'UCI', // mismo código
                'name' => 'UCI Actualizada',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'UCI Actualizada');
    }
}
