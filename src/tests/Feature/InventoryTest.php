<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\KitTransactionModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_registrar_entrada_actualiza_stock(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'product_id'      => $product->id,
                'warehouse_id'    => $setup['warehouse']->id,
                'location_id'     => $setup['location']->id,
                'lot_number'      => 'LOT-ENTRY-001',
                'expiration_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity_base'   => 100,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('batches', [
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-ENTRY-001',
            'quantity_available' => 100,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 100,
        ]);
    }

    public function test_salida_fefo_toma_lote_con_vencimiento_mas_proximo(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batchSoon = $this->createBatch($product->id, 'LOT-SOON', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($product->id, 'LOT-LATER', now()->addDays(90)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 30,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $batchSoon->refresh();
        $this->assertSame(20, $batchSoon->quantity_available);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'    => $product->id,
            'batch_id'      => $batchSoon->id,
            'movement_type' => 'exit',
            'quantity'      => -30,
        ]);
    }

    public function test_salida_con_stock_insuficiente_retorna_error(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $this->createBatch($product->id, 'LOT-LOW', now()->addDays(30)->format('Y-m-d'), 10, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 100,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_salida_kit_descuenta_componentes_y_crea_kit_transaction(): void
    {
        $setup = $this->createWarehouseSetup();
        $kit = ProductModel::where('code', 'KIT-CIR-BAS')->first();
        $gasa = ProductModel::where('code', 'GAS-10X10')->first();
        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        $this->createBatch($gasa->id, 'LOT-GASA', now()->addMonths(3)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($aguja->id, 'LOT-AGUJA', now()->addMonths(3)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($gasa->id, $setup['warehouse']->id);
        $this->recalculateStock($aguja->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $kit->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 2,
                'reason'         => 'Cirugía test',
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kit_transaction_id', fn ($id) => $id > 0);

        $this->assertSame(1, KitTransactionModel::count());

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $gasa->id,
            'movement_type'  => 'exit',
            'quantity'       => -10,
            'reference_type' => 'kit_transaction',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $aguja->id,
            'movement_type'  => 'exit',
            'quantity'       => -20,
            'reference_type' => 'kit_transaction',
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $gasa->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 40,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $aguja->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 80,
        ]);
    }

    public function test_salida_no_selecciona_lote_vencido_aunque_sea_el_mas_proximo(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        // Lote vencido ayer pero aún con status 'active' (job sga:check-expirations sin ejecutar).
        $batchExpired = $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $batchValid = $this->createBatch($product->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 30,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $batchValid->refresh();
        $batchExpired->refresh();
        $this->assertSame(70, $batchValid->quantity_available);
        $this->assertSame(50, $batchExpired->quantity_available);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'    => $product->id,
            'batch_id'      => $batchValid->id,
            'movement_type' => 'exit',
            'quantity'      => -30,
        ]);
    }

    public function test_salida_falla_si_todo_el_stock_esta_vencido(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 10,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'EXPIRED_STOCK');
    }

    public function test_transferencia_falla_si_todo_el_stock_esta_vencido(): void
    {
        $origen = $this->createWarehouseSetup('-ORIGEN');
        $destino = $this->createWarehouseSetup('-DESTINO');
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $origen['location'], $origen['warehouse']->id);
        $this->recalculateStock($product->id, $origen['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/transfer', [
                'product_id'        => $product->id,
                'warehouse_from_id' => $origen['warehouse']->id,
                'warehouse_to_id'   => $destino['warehouse']->id,
                'location_from_id'  => $origen['location']->id,
                'location_to_id'    => $destino['location']->id,
                'quantity'          => 10,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'EXPIRED_STOCK');
    }

    public function test_devolucion_permite_gestionar_lote_vencido(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batchExpired = $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/return', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'quantity'     => 10,
                'reason'       => 'Devolución de producto vencido al proveedor',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $batchExpired->refresh();
        $this->assertSame(40, $batchExpired->quantity_available);
    }

    public function test_ajuste_negativo_permite_gestionar_lote_vencido(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batchExpired = $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/adjustment', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'quantity'     => -10,
                'reason'       => 'Ajuste por conteo físico: lote vencido',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $batchExpired->refresh();
        $this->assertSame(40, $batchExpired->quantity_available);
    }

    public function test_listado_de_lotes_por_producto_filtra_disponibles_para_salida(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batchExpired = $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $batchValid = $this->createBatch($product->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/v1/products/{$product->id}/batches?available_for_exit=1")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($batchValid->id));
        $this->assertFalse($ids->contains($batchExpired->id));
    }

    public function test_stock_summary_excluye_lote_vencido(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'total_quantity'     => 0,
            'available_quantity' => 0,
        ]);
    }

    public function test_stock_summary_incluye_expired_quantity(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $this->createBatch($product->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 30, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/stock/summary?'.http_build_query([
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.available_quantity', 30)
            ->assertJsonPath('data.expired_quantity', 50);
    }

    public function test_baja_descuenta_stock_vigente_por_dano(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batch = $this->createBatch($product->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'batch_id'     => $batch->id,
                'quantity'     => 5,
                'reason'       => 'Producto dañado: caja golpeada',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.movement_type', 'loss')
            ->assertJsonPath('data.batch_id', $batch->id)
            ->assertJsonPath('data.quantity', -5);

        $batch->refresh();
        $this->assertSame(45, $batch->quantity_available);
        $this->assertSame(45, $batch->locations()->first()->pivot->quantity);
    }

    public function test_baja_permite_gestionar_lote_vencido(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batchExpired = $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'batch_id'     => $batchExpired->id,
                'quantity'     => 10,
                'reason'       => 'Producto vencido',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $batchExpired->refresh();
        $this->assertSame(40, $batchExpired->quantity_available);
    }

    public function test_baja_con_stock_insuficiente_retorna_error(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batch = $this->createBatch($product->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 5, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'batch_id'     => $batch->id,
                'quantity'     => 10,
                'reason'       => 'Pérdida',
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $batch->refresh();
        $this->assertSame(5, $batch->quantity_available);
    }

    public function test_baja_permite_elegir_lote_especifico_sin_aplicar_fefo(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        // FEFO elegiría primero el lote que vence antes.
        $batchExpiresFirst = $this->createBatch($product->id, 'LOT-FEFO-1', now()->addDays(10)->format('Y-m-d'), 30, $setup['location'], $setup['warehouse']->id);
        $batchExpiresLater = $this->createBatch($product->id, 'LOT-FEFO-2', now()->addDays(60)->format('Y-m-d'), 30, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        // El usuario elige explícitamente el lote que vence más tarde.
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'batch_id'     => $batchExpiresLater->id,
                'quantity'     => 10,
                'reason'       => 'Producto dañado',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.batch_id', $batchExpiresLater->id);

        $batchExpiresFirst->refresh();
        $batchExpiresLater->refresh();

        // El lote priorizado por FEFO no se modifica; solo el seleccionado.
        $this->assertSame(30, $batchExpiresFirst->quantity_available);
        $this->assertSame(20, $batchExpiresLater->quantity_available);
    }

    public function test_baja_valida_cantidad_contra_la_ubicacion_del_lote(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $otherLocation = LocationModel::create([
            'zone_id'   => $setup['zone']->id,
            'name'      => 'Ubicación Inventario 2',
            'code'      => 'U-INV2',
            'is_active' => true,
        ]);

        // El lote tiene 20 unidades disponibles en total, pero distribuidas
        // entre dos ubicaciones: solo 5 en la ubicación seleccionada.
        $batch = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-SPLIT',
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => 20,
            'quantity_available' => 20,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $batch->locations()->attach($setup['location']->id, ['quantity' => 5]);
        $batch->locations()->attach($otherLocation->id, ['quantity' => 15]);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        // Se solicitan 10 unidades de la ubicación que solo tiene 5.
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'batch_id'     => $batch->id,
                'quantity'     => 10,
                'reason'       => 'Pérdida',
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $batch->refresh();
        $this->assertSame(20, $batch->quantity_available);
    }

    public function test_baja_requiere_batch_id(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $this->createBatch($product->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'quantity'     => 5,
                'reason'       => 'Producto dañado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['batch_id']);
    }

    public function test_baja_rechaza_lote_de_otro_producto(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $otherProduct = ProductModel::where('code', '!=', 'AGU-21G')->first();

        $this->createBatch($product->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $otherBatch = $this->createBatch($otherProduct->id, 'LOT-OTHER', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'batch_id'     => $otherBatch->id,
                'quantity'     => 5,
                'reason'       => 'Producto dañado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['batch_id']);
    }

    /**
     * @return array{warehouse: WarehouseModel, zone: ZoneModel, location: LocationModel}
     */
    private function createWarehouseSetup(string $suffix = ''): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => "Almacén Inventario Test{$suffix}",
            'code'      => "ALM-INV{$suffix}",
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => "Zona Inventario{$suffix}",
            'code'         => "Z-INV{$suffix}",
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $location = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => "Ubicación Inventario{$suffix}",
            'code'      => "U-INV{$suffix}",
            'is_active' => true,
        ]);

        return compact('warehouse', 'zone', 'location');
    }

    private function createBatch(int $productId, string $lotNumber, string $expirationDate, int $quantity, LocationModel $location, int $warehouseId): BatchModel
    {
        $batch = BatchModel::create([
            'product_id'         => $productId,
            'lot_number'         => $lotNumber,
            'expiration_date'    => $expirationDate,
            'quantity_received'  => $quantity,
            'quantity_available' => $quantity,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batch->locations()->attach($location->id, ['quantity' => $quantity]);

        StockMovementModel::create([
            'warehouse_id'   => $warehouseId,
            'product_id'     => $productId,
            'batch_id'       => $batch->id,
            'location_to_id' => $location->id,
            'movement_type'  => 'entry',
            'quantity'       => $quantity,
            'user_id'        => UserModel::first()->id,
        ]);

        return $batch;
    }

    private function recalculateStock(int $productId, int $warehouseId): void
    {
        app(\App\Modules\Inventory\Domain\Services\StockCalculator::class)
            ->recalculateSummary($productId, $warehouseId);
    }
}
