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

    // ── Almacenes ─────────────────────────────────────────────────────────────

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

    // ── Zonas ─────────────────────────────────────────────────────────────────

    public function test_crud_zonas_y_validacion_temperatura(): void
    {
        $warehouseId = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', ['name' => 'Alm Zona', 'code' => 'ALM-ZON'])
            ->json('data.id');

        // temp_min > temp_max → error
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

    // ── Ubicaciones ───────────────────────────────────────────────────────────

    public function test_crud_ubicaciones_sin_dimensiones(): void
    {
        [$warehouseId, $zoneId] = $this->createWarehouseAndZone('ALM-UB1', 'Z-UB1');

        $locationResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/locations', [
                'zone_id' => $zoneId,
                'name'    => 'Estante A1',
                'code'    => 'A1',
            ]);
        $locationResponse->assertStatus(201);
        $locationId = $locationResponse->json('data.id');

        // Los campos físicos deben ser null cuando no se envían
        $this->assertNull($locationResponse->json('data.volume_cm3'));
        $this->assertNull($locationResponse->json('data.max_weight_kg'));

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

    public function test_crud_ubicaciones_con_dimensiones_fisicas(): void
    {
        [, $zoneId] = $this->createWarehouseAndZone('ALM-DIM', 'Z-DIM');

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/locations', [
                'zone_id'       => $zoneId,
                'name'          => 'Estante con dimensiones',
                'code'          => 'EST-DIM',
                'volume_cm3'    => 50000.00,
                'max_weight_kg' => 200.00,
                'description'   => 'Prueba con dimensiones físicas',
            ]);

        $response->assertStatus(201);
        $this->assertEquals(50000.0, (float) $response->json('data.volume_cm3'));
        $this->assertEquals(200.0,   (float) $response->json('data.max_weight_kg'));

        $locationId = $response->json('data.id');

        // Actualizar dimensiones
        $updated = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/locations/{$locationId}", [
                'zone_id'       => $zoneId,
                'name'          => 'Estante actualizado',
                'code'          => 'EST-DIM',
                'volume_cm3'    => 60000.00,
                'max_weight_kg' => 250.00,
            ])
            ->assertStatus(200);
        $this->assertEquals(60000.0, (float) $updated->json('data.volume_cm3'));
        $this->assertEquals(250.0,   (float) $updated->json('data.max_weight_kg'));

        // Persistido correctamente
        $this->assertDatabaseHas('locations', [
            'id'            => $locationId,
            'volume_cm3'    => 60000.00,
            'max_weight_kg' => 250.00,
        ]);
    }

    public function test_dimensiones_negativas_retornan_422(): void
    {
        [, $zoneId] = $this->createWarehouseAndZone('ALM-NEG', 'Z-NEG');

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/locations', [
                'zone_id'       => $zoneId,
                'name'          => 'Estante inválido',
                'code'          => 'EST-NEG',
                'volume_cm3'    => -100,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/locations', [
                'zone_id'       => $zoneId,
                'name'          => 'Estante inválido',
                'code'          => 'EST-NEG2',
                'max_weight_kg' => -50,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_ubicacion_sin_zona_retorna_422(): void
    {
        $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/locations', [
                'zone_id' => 99999,
                'name'    => 'Sin zona',
                'code'    => 'SZ-001',
            ])
            ->assertStatus(422);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function createWarehouseAndZone(string $wCode, string $zCode): array
    {
        $warehouseId = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/warehouses', ['name' => "Almacén {$wCode}", 'code' => $wCode])
            ->json('data.id');

        $zoneId = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/zones', [
                'warehouse_id' => $warehouseId,
                'name'         => "Zona {$zCode}",
                'code'         => $zCode,
                'type'         => 'ambient',
            ])
            ->json('data.id');

        return [$warehouseId, $zoneId];
    }
}
