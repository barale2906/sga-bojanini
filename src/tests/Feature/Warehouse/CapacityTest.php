<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapacityTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createWarehouse(string $code = 'ALM-CAP'): WarehouseModel
    {
        return WarehouseModel::create([
            'name'      => "Almacén {$code}",
            'code'      => $code,
            'is_active' => true,
        ]);
    }

    private function createZone(int $warehouseId, string $code = 'Z-CAP'): ZoneModel
    {
        return ZoneModel::create([
            'warehouse_id' => $warehouseId,
            'name'         => "Zona {$code}",
            'code'         => $code,
            'type'         => 'ambient',
            'is_active'    => true,
        ]);
    }

    private function createLocation(
        int $zoneId,
        string $code,
        ?float $volumeCm3 = null,
        ?float $maxWeightKg = null
    ): LocationModel {
        return LocationModel::create([
            'zone_id'       => $zoneId,
            'name'          => "Ubicación {$code}",
            'code'          => $code,
            'volume_cm3'    => $volumeCm3,
            'max_weight_kg' => $maxWeightKg,
            'is_active'     => true,
        ]);
    }

    private function createProduct(
        string $code,
        ?float $volumeCm3 = null,
        ?float $weightKg = null
    ): ProductModel {
        $cat  = CategoryModel::first();
        $unit = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        return ProductModel::create([
            'category_id'  => $cat->id,
            'base_unit_id' => $unit->id,
            'product_type' => 'simple',
            'name'         => "Producto {$code}",
            'code'         => $code,
            'volume_cm3'   => $volumeCm3,
            'weight_kg'    => $weightKg,
            'is_active'    => true,
        ]);
    }

    private function createActiveBatch(int $productId, LocationModel $location, int $quantity): BatchModel
    {
        $batch = BatchModel::create([
            'product_id'         => $productId,
            'lot_number'         => 'LOT-'.uniqid(),
            'expiration_date'    => now()->addYear()->format('Y-m-d'),
            'quantity_received'  => $quantity,
            'quantity_available' => $quantity,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batch->locations()->attach($location->id, ['quantity' => $quantity]);

        return $batch;
    }

    /** Extrae un valor numérico del JSON y lo convierte a float para comparaciones exactas. */
    private function floatOf($response, string $path): float
    {
        return (float) $response->json($path);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests de Ubicación
    // ─────────────────────────────────────────────────────────────────────────

    public function test_capacidad_ubicacion_sin_stock_retorna_ceros(): void
    {
        $wh  = $this->createWarehouse('ALM-C01');
        $z   = $this->createZone($wh->id, 'Z-C01');
        $loc = $this->createLocation($z->id, 'L-C01', 50000.0, 200.0);

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/locations/{$loc->id}/capacity")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $loc->id);

        $this->assertEquals(0.0,     $this->floatOf($resp, 'data.capacity_volume.used_cm3'));
        $this->assertEquals(50000.0, $this->floatOf($resp, 'data.capacity_volume.max_cm3'));
        $this->assertEquals(50000.0, $this->floatOf($resp, 'data.capacity_volume.available_cm3'));
        $this->assertEquals(0.0,     $this->floatOf($resp, 'data.capacity_volume.usage_pct'));

        $this->assertEquals(0.0,   $this->floatOf($resp, 'data.capacity_weight.used_kg'));
        $this->assertEquals(200.0, $this->floatOf($resp, 'data.capacity_weight.max_kg'));
        $this->assertEquals(200.0, $this->floatOf($resp, 'data.capacity_weight.available_kg'));
        $this->assertEquals(0.0,   $this->floatOf($resp, 'data.capacity_weight.usage_pct'));
    }

    public function test_capacidad_ubicacion_calcula_uso_correctamente(): void
    {
        $wh      = $this->createWarehouse('ALM-C02');
        $z       = $this->createZone($wh->id, 'Z-C02');
        $loc     = $this->createLocation($z->id, 'L-C02', 50000.0, 200.0);
        $product = $this->createProduct('PRD-C02', 1000.0, 2.0); // 1000 cm³/u, 2 kg/u

        // Lote de 10 unidades → 10 000 cm³ y 20 kg usados
        $this->createActiveBatch($product->id, $loc, 10);

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/locations/{$loc->id}/capacity")
            ->assertOk();

        $this->assertEquals(10000.0, $this->floatOf($resp, 'data.capacity_volume.used_cm3'));
        $this->assertEquals(40000.0, $this->floatOf($resp, 'data.capacity_volume.available_cm3'));
        $this->assertEquals(20.0,    $this->floatOf($resp, 'data.capacity_volume.usage_pct'));

        $this->assertEquals(20.0,  $this->floatOf($resp, 'data.capacity_weight.used_kg'));
        $this->assertEquals(180.0, $this->floatOf($resp, 'data.capacity_weight.available_kg'));
        $this->assertEquals(10.0,  $this->floatOf($resp, 'data.capacity_weight.usage_pct'));
    }

    public function test_capacidad_ubicacion_acumula_multiples_lotes(): void
    {
        $wh      = $this->createWarehouse('ALM-C03');
        $z       = $this->createZone($wh->id, 'Z-C03');
        $loc     = $this->createLocation($z->id, 'L-C03', 50000.0, 200.0);
        $product = $this->createProduct('PRD-C03', 500.0, 1.0);

        // Dos lotes: 10 + 20 unidades → 15 000 cm³ y 30 kg
        $this->createActiveBatch($product->id, $loc, 10);
        $this->createActiveBatch($product->id, $loc, 20);

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/locations/{$loc->id}/capacity")
            ->assertOk();

        $this->assertEquals(15000.0, $this->floatOf($resp, 'data.capacity_volume.used_cm3'));
        $this->assertEquals(30.0,    $this->floatOf($resp, 'data.capacity_weight.used_kg'));
    }

    public function test_capacidad_excluye_lotes_no_activos(): void
    {
        $wh      = $this->createWarehouse('ALM-C04');
        $z       = $this->createZone($wh->id, 'Z-C04');
        $loc     = $this->createLocation($z->id, 'L-C04', 50000.0, 200.0);
        $product = $this->createProduct('PRD-C04', 1000.0, 2.0);

        // Lote activo: 10 u
        $this->createActiveBatch($product->id, $loc, 10);

        // Lote expirado → NO debe contar
        $expired = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-EXP-'.uniqid(),
            'expiration_date'    => now()->subDay()->format('Y-m-d'),
            'quantity_received'  => 50,
            'quantity_available' => 0,
            'status'             => 'expired',
            'received_at'        => now()->subMonths(2),
        ]);
        $expired->locations()->attach($loc->id, ['quantity' => 50]);

        // Lote agotado → NO debe contar
        $depleted = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-DEP-'.uniqid(),
            'expiration_date'    => now()->addYear()->format('Y-m-d'),
            'quantity_received'  => 30,
            'quantity_available' => 0,
            'status'             => 'depleted',
            'received_at'        => now()->subMonth(),
        ]);
        $depleted->locations()->attach($loc->id, ['quantity' => 30]);

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/locations/{$loc->id}/capacity")
            ->assertOk();

        // Solo el lote activo: 10 u × 1000 cm³ = 10 000 cm³, 10 u × 2 kg = 20 kg
        $this->assertEquals(10000.0, $this->floatOf($resp, 'data.capacity_volume.used_cm3'));
        $this->assertEquals(20.0,    $this->floatOf($resp, 'data.capacity_weight.used_kg'));
    }

    public function test_capacidad_ubicacion_sin_max_retorna_null_en_max_y_pct(): void
    {
        $wh      = $this->createWarehouse('ALM-C05');
        $z       = $this->createZone($wh->id, 'Z-C05');
        $loc     = $this->createLocation($z->id, 'L-C05', null, null); // sin límites
        $product = $this->createProduct('PRD-C05', 500.0, 1.0);

        $this->createActiveBatch($product->id, $loc, 5); // 2500 cm³, 5 kg

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/locations/{$loc->id}/capacity")
            ->assertOk();

        $this->assertNull($resp->json('data.capacity_volume.max_cm3'));
        $this->assertNull($resp->json('data.capacity_volume.available_cm3'));
        $this->assertNull($resp->json('data.capacity_volume.usage_pct'));
        $this->assertEquals(2500.0, $this->floatOf($resp, 'data.capacity_volume.used_cm3'));

        $this->assertNull($resp->json('data.capacity_weight.max_kg'));
        $this->assertNull($resp->json('data.capacity_weight.available_kg'));
        $this->assertNull($resp->json('data.capacity_weight.usage_pct'));
        $this->assertEquals(5.0, $this->floatOf($resp, 'data.capacity_weight.used_kg'));
    }

    public function test_capacidad_ubicacion_no_encontrada_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/locations/99999/capacity')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests de Zona
    // ─────────────────────────────────────────────────────────────────────────

    public function test_capacidad_zona_agrega_todas_las_ubicaciones(): void
    {
        $wh      = $this->createWarehouse('ALM-Z01');
        $z       = $this->createZone($wh->id, 'Z-Z01');
        $loc1    = $this->createLocation($z->id, 'L-Z1A', 30000.0, 100.0);
        $loc2    = $this->createLocation($z->id, 'L-Z1B', 20000.0,  80.0);
        $product = $this->createProduct('PRD-Z01', 1000.0, 2.0);

        $this->createActiveBatch($product->id, $loc1, 5);  // 5 000 cm³, 10 kg
        $this->createActiveBatch($product->id, $loc2, 8);  // 8 000 cm³, 16 kg

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/zones/{$z->id}/capacity")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $z->id);

        // Totales: max = 50 000, usado = 13 000
        $this->assertEquals(50000.0, $this->floatOf($resp, 'data.capacity_volume.max_cm3'));
        $this->assertEquals(13000.0, $this->floatOf($resp, 'data.capacity_volume.used_cm3'));
        $this->assertEquals(37000.0, $this->floatOf($resp, 'data.capacity_volume.available_cm3'));
        $this->assertEquals(26.0,    $this->floatOf($resp, 'data.capacity_volume.usage_pct'));

        $this->assertEquals(180.0, $this->floatOf($resp, 'data.capacity_weight.max_kg'));
        $this->assertEquals(26.0,  $this->floatOf($resp, 'data.capacity_weight.used_kg'));

        $this->assertCount(2, $resp->json('data.locations'));
    }

    public function test_capacidad_zona_sin_ubicaciones_retorna_ceros_y_null(): void
    {
        $wh = $this->createWarehouse('ALM-Z02');
        $z  = $this->createZone($wh->id, 'Z-Z02');

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/zones/{$z->id}/capacity")
            ->assertOk();

        $this->assertEquals(0.0, $this->floatOf($resp, 'data.capacity_volume.used_cm3'));
        $this->assertNull($resp->json('data.capacity_volume.max_cm3')); // sin ubicaciones → sin max
        $this->assertCount(0, $resp->json('data.locations'));
    }

    public function test_capacidad_zona_no_encontrada_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/zones/99999/capacity')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tests de Almacén
    // ─────────────────────────────────────────────────────────────────────────

    public function test_capacidad_almacen_agrega_todas_las_zonas(): void
    {
        $wh      = $this->createWarehouse('ALM-W01');
        $z1      = $this->createZone($wh->id, 'Z-W1A');
        $z2      = $this->createZone($wh->id, 'Z-W1B');
        $loc1    = $this->createLocation($z1->id, 'L-W1A', 40000.0, 150.0);
        $loc2    = $this->createLocation($z2->id, 'L-W1B', 60000.0, 250.0);
        $product = $this->createProduct('PRD-W01', 2000.0, 5.0);

        $this->createActiveBatch($product->id, $loc1, 3);  // 6 000 cm³, 15 kg
        $this->createActiveBatch($product->id, $loc2, 7);  // 14 000 cm³, 35 kg

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/warehouses/{$wh->id}/capacity")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $wh->id);

        // Global: max = 100 000, usado = 20 000
        $this->assertEquals(100000.0, $this->floatOf($resp, 'data.capacity_volume.max_cm3'));
        $this->assertEquals(20000.0,  $this->floatOf($resp, 'data.capacity_volume.used_cm3'));
        $this->assertEquals(80000.0,  $this->floatOf($resp, 'data.capacity_volume.available_cm3'));
        $this->assertEquals(20.0,     $this->floatOf($resp, 'data.capacity_volume.usage_pct'));

        $this->assertEquals(400.0, $this->floatOf($resp, 'data.capacity_weight.max_kg'));
        $this->assertEquals(50.0,  $this->floatOf($resp, 'data.capacity_weight.used_kg'));

        $this->assertCount(2, $resp->json('data.zones'));
    }

    public function test_capacidad_almacen_sin_zonas_retorna_ceros_y_null(): void
    {
        $wh = $this->createWarehouse('ALM-W02');

        $resp = $this->withHeaders($this->auth())
            ->getJson("/api/v1/warehouses/{$wh->id}/capacity")
            ->assertOk();

        $this->assertEquals(0.0, $this->floatOf($resp, 'data.capacity_volume.used_cm3'));
        $this->assertNull($resp->json('data.capacity_volume.max_cm3'));
        $this->assertCount(0, $resp->json('data.zones'));
    }

    public function test_capacidad_almacen_no_encontrado_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/warehouses/99999/capacity')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Productos con dimensiones
    // ─────────────────────────────────────────────────────────────────────────

    public function test_producto_guarda_y_retorna_dimensiones(): void
    {
        $cat  = CategoryModel::first();
        $unit = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $resp = $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', [
                'category_id'  => $cat->id,
                'base_unit_id' => $unit->id,
                'product_type' => 'simple',
                'name'         => 'Producto Dimensional',
                'code'         => 'PRD-DIM-001',
                'volume_cm3'   => 1500.00,
                'weight_kg'    => 3.50,
            ]);

        $resp->assertStatus(201);
        $this->assertEquals(1500.0, $this->floatOf($resp, 'data.volume_cm3'));
        $this->assertEquals(3.5,    $this->floatOf($resp, 'data.weight_kg'));

        $this->assertDatabaseHas('products', [
            'code'       => 'PRD-DIM-001',
            'volume_cm3' => 1500.00,
            'weight_kg'  => 3.50,
        ]);
    }

    public function test_producto_sin_dimensiones_retorna_null(): void
    {
        $cat  = CategoryModel::first();
        $unit = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $resp = $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', [
                'category_id'  => $cat->id,
                'base_unit_id' => $unit->id,
                'name'         => 'Producto Sin Dimensiones',
                'code'         => 'PRD-NODIM-001',
            ]);

        $resp->assertStatus(201);
        $this->assertNull($resp->json('data.volume_cm3'));
        $this->assertNull($resp->json('data.weight_kg'));
    }

    public function test_dimensiones_negativas_en_producto_retornan_422(): void
    {
        $cat  = CategoryModel::first();
        $unit = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', [
                'category_id'  => $cat->id,
                'base_unit_id' => $unit->id,
                'name'         => 'Producto inválido V',
                'code'         => 'PRD-NEG-001',
                'volume_cm3'   => -10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', [
                'category_id'  => $cat->id,
                'base_unit_id' => $unit->id,
                'name'         => 'Producto inválido W',
                'code'         => 'PRD-NEG-002',
                'weight_kg'    => -5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_actualizar_producto_modifica_dimensiones(): void
    {
        $cat  = CategoryModel::first();
        $unit = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $productId = $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', [
                'category_id'  => $cat->id,
                'base_unit_id' => $unit->id,
                'name'         => 'Producto Actualizable',
                'code'         => 'PRD-UPD-DIM',
                'volume_cm3'   => 1000.0,
                'weight_kg'    => 2.0,
            ])
            ->json('data.id');

        $resp = $this->withHeaders($this->auth())
            ->putJson("/api/v1/products/{$productId}", [
                'category_id'  => $cat->id,
                'base_unit_id' => $unit->id,
                'name'         => 'Producto Actualizable',
                'code'         => 'PRD-UPD-DIM',
                'volume_cm3'   => 2000.0,
                'weight_kg'    => 4.5,
            ])
            ->assertOk();

        $this->assertEquals(2000.0, $this->floatOf($resp, 'data.volume_cm3'));
        $this->assertEquals(4.5,    $this->floatOf($resp, 'data.weight_kg'));
    }
}
