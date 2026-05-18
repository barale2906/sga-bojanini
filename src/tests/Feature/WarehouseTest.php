<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_crud_almacenes(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', [
                'name'    => 'Almacén Principal',
                'code'    => 'ALM-001',
                'address' => 'Calle 123',
            ]);
        $response->assertStatus(201)->assertJson(['success' => true]);
        $warehouseId = $response->json('data.id');

        $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/warehouses')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/warehouses/{$warehouseId}")
            ->assertStatus(200)
            ->assertJsonPath('data.code', 'ALM-001');

        $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/warehouses/{$warehouseId}", [
                'name' => 'Almacén Actualizado',
                'code' => 'ALM-001',
            ])
            ->assertStatus(200);

        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/warehouses/{$warehouseId}")
            ->assertStatus(200);
    }

    public function test_codigo_duplicado_retorna_422(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', [
                'name' => 'Almacén A',
                'code' => 'ALM-DUP',
            ])
            ->assertStatus(201);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', [
                'name' => 'Almacén B',
                'code' => 'ALM-DUP',
            ])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_eliminar_almacen_con_zonas_hace_cascade_soft_delete(): void
    {
        $warehouse = WarehouseModel::create([
            'name'      => 'Almacén Cascade',
            'code'      => 'ALM-CAS',
            'is_active' => true,
        ]);

        ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => 'Zona Fría',
            'code'         => 'Z-FR',
            'type'         => 'cold',
            'is_active'    => true,
        ]);

        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/warehouses/{$warehouse->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }

    public function test_crud_zonas_y_validacion_temperatura(): void
    {
        $warehouseId = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', ['name' => 'Alm Zona', 'code' => 'ALM-ZON'])
            ->json('data.id');

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/zones', [
                'warehouse_id' => $warehouseId,
                'name'         => 'Zona Ambiente',
                'code'         => 'Z-AMB',
                'type'         => 'ambient',
                'temp_max'     => 5,
                'temp_min'     => 10,
            ])
            ->assertStatus(422);

        $zoneResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/zones', [
                'warehouse_id' => $warehouseId,
                'name'         => 'Zona Fría',
                'code'         => 'Z-FRI',
                'type'         => 'cold',
                'temp_min'     => 2,
                'temp_max'     => 8,
            ]);
        $zoneResponse->assertStatus(201);
        $zoneId = $zoneResponse->json('data.id');

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/warehouses/{$warehouseId}/zones")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/zones/{$zoneId}", [
                'warehouse_id' => $warehouseId,
                'name'         => 'Zona Fría Actualizada',
                'code'         => 'Z-FRI',
                'type'         => 'cold',
                'temp_min'     => 1,
                'temp_max'     => 6,
            ])
            ->assertStatus(200);
    }

    public function test_crud_ubicaciones(): void
    {
        $warehouseId = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', ['name' => 'Alm Ubic', 'code' => 'ALM-UB'])
            ->json('data.id');

        $zoneId = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/zones', [
                'warehouse_id' => $warehouseId,
                'name'         => 'Zona 1',
                'code'         => 'Z1',
                'type'         => 'ambient',
            ])
            ->json('data.id');

        $locationResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/locations', [
                'zone_id'  => $zoneId,
                'name'     => 'Estante A1',
                'code'     => 'A1',
                'capacity' => 100,
            ]);
        $locationResponse->assertStatus(201);
        $locationId = $locationResponse->json('data.id');

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/warehouses/{$warehouseId}/locations")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/locations/{$locationId}")
            ->assertStatus(200)
            ->assertJsonPath('data.code', 'A1');

        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/locations/{$locationId}")
            ->assertStatus(200);
    }
}
