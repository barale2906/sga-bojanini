<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\KitTransactionModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 30,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmMovement($response->json('data.0.id'));

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

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 30,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmMovement($response->json('data.0.id'));

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

    /**
     * Cuando FEFO necesita tomar de dos lotes para cubrir la cantidad solicitada,
     * se debe crear un StockMovement independiente por cada lote, con su propio
     * batch_id y la cantidad exacta tomada de ese lote.
     */
    public function test_salida_simple_multi_lote_crea_un_movimiento_por_lote(): void
    {
        $setup   = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $center  = CostCenterModel::where('type', 'internal')->first();

        // Lote A vence antes → FEFO lo toma primero; solo tiene 5 unidades.
        $lotA = $this->createBatch($product->id, 'LOT-MULTI-A', now()->addDays(10)->format('Y-m-d'), 5, $setup['location'], $setup['warehouse']->id);
        // Lote B vence después; tiene suficiente para el resto.
        $lotB = $this->createBatch($product->id, 'LOT-MULTI-B', now()->addDays(60)->format('Y-m-d'), 20, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 12,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $movementIds = collect($response->json('data'))->pluck('id')->all();
        $this->confirmMovements($movementIds);

        // Dos movimientos: uno por cada lote consumido.
        $movements = StockMovementModel::where('product_id', $product->id)
            ->where('movement_type', 'exit')
            ->get();

        $this->assertCount(2, $movements);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'batch_id'   => $lotA->id,
            'quantity'   => -5,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'batch_id'   => $lotB->id,
            'quantity'   => -7,
        ]);

        $lotA->refresh();
        $lotB->refresh();

        $this->assertSame(0, $lotA->quantity_available);
        $this->assertSame('depleted', $lotA->status);
        $this->assertSame(13, $lotB->quantity_available);
    }

    /**
     * Cuando FEFO necesita tomar de dos lotes para cubrir una transferencia,
     * se crea un StockMovement por cada lote con su cantidad exacta.
     */
    public function test_transferencia_multi_lote_crea_un_movimiento_por_lote(): void
    {
        $origen  = $this->createWarehouseSetup('-ORIGEN-ML');
        $destino = $this->createWarehouseSetup('-DESTINO-ML');
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $lotA = $this->createBatch($product->id, 'LOT-TRANS-A', now()->addDays(10)->format('Y-m-d'), 8, $origen['location'], $origen['warehouse']->id);
        $lotB = $this->createBatch($product->id, 'LOT-TRANS-B', now()->addDays(60)->format('Y-m-d'), 15, $origen['location'], $origen['warehouse']->id);

        $this->recalculateStock($product->id, $origen['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/transfer', [
                'product_id'        => $product->id,
                'warehouse_from_id' => $origen['warehouse']->id,
                'warehouse_to_id'   => $destino['warehouse']->id,
                'location_from_id'  => $origen['location']->id,
                'location_to_id'    => $destino['location']->id,
                'quantity'          => 10,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $movementIds = collect($response->json('data'))->pluck('id')->all();
        $this->confirmMovements($movementIds);

        $movements = StockMovementModel::where('product_id', $product->id)
            ->where('movement_type', 'transfer')
            ->get();

        $this->assertCount(2, $movements);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'       => $product->id,
            'batch_id'         => $lotA->id,
            'quantity'         => 8,
            'warehouse_to_id'  => $destino['warehouse']->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'       => $product->id,
            'batch_id'         => $lotB->id,
            'quantity'         => 2,
            'warehouse_to_id'  => $destino['warehouse']->id,
        ]);

        // En transferencias el quantity_available del batch no cambia (el stock
        // se mueve de ubicación, no sale del sistema). Lo que cambia es batch_location.
        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $lotA->id,
            'location_id' => $origen['location']->id,
            'quantity'    => 0,
        ]);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $lotA->id,
            'location_id' => $destino['location']->id,
            'quantity'    => 8,
        ]);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $lotB->id,
            'location_id' => $origen['location']->id,
            'quantity'    => 13,
        ]);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $lotB->id,
            'location_id' => $destino['location']->id,
            'quantity'    => 2,
        ]);
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

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/return', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'quantity'     => 10,
                'reason'       => 'Devolución de producto vencido al proveedor',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmMovement($response->json('data.id'));

        $batchExpired->refresh();
        $this->assertSame(40, $batchExpired->quantity_available);
    }

    public function test_ajuste_negativo_permite_gestionar_lote_vencido(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batchExpired = $this->createBatch($product->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/adjustment', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'quantity'     => -10,
                'reason'       => 'Ajuste por conteo físico: lote vencido',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmMovement($response->json('data.id'));

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

        $response = $this->withHeaders($this->auth())
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

        $this->confirmMovement($response->json('data.id'));

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

        $response = $this->withHeaders($this->auth())
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

        $this->confirmMovement($response->json('data.id'));

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
        $response = $this->withHeaders($this->auth())
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

        $this->confirmMovement($response->json('data.id'));

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

    public function test_devolucion_sin_ubicacion_actualiza_resumen_de_stock(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batch = $this->createBatch($product->id, 'LOT-RETURN', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/return', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'quantity'     => 10,
                'reason'       => 'Devolución sin ubicación específica',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmMovement($response->json('data.id'));

        $batch->refresh();
        $this->assertSame(40, $batch->quantity_available);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $batch->id,
            'location_id' => $setup['location']->id,
            'quantity'    => 40,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 40,
        ]);
    }

    public function test_ajuste_negativo_reparte_descuento_entre_ubicaciones_sin_dejar_negativos(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $locationB = $this->createLocation($setup['zone'], 'B');

        $batch = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-SPLIT',
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => 100,
            'quantity_available' => 100,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $batch->locations()->attach($setup['location']->id, ['quantity' => 60]);
        $batch->locations()->attach($locationB->id, ['quantity' => 40]);

        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/adjustment', [
                'product_id'   => $product->id,
                'warehouse_id' => $setup['warehouse']->id,
                'location_id'  => $setup['location']->id,
                'quantity'     => -70,
                'reason'       => 'Conteo físico: faltante repartido entre ubicaciones',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmMovement($response->json('data.id'));

        $batch->refresh();
        $this->assertSame(30, $batch->quantity_available);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $batch->id,
            'location_id' => $setup['location']->id,
            'quantity'    => 0,
        ]);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $batch->id,
            'location_id' => $locationB->id,
            'quantity'    => 30,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 30,
        ]);
    }

    public function test_transferencia_reparte_descuento_entre_ubicaciones_sin_dejar_negativos(): void
    {
        $origen = $this->createWarehouseSetup('-ORIGEN-MULTI');
        $destino = $this->createWarehouseSetup('-DESTINO-MULTI');
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $locationB = $this->createLocation($origen['zone'], 'B-ORIGEN-MULTI');

        $batch = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-SPLIT-TRANSFER',
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => 100,
            'quantity_available' => 100,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $batch->locations()->attach($origen['location']->id, ['quantity' => 60]);
        $batch->locations()->attach($locationB->id, ['quantity' => 40]);

        $this->recalculateStock($product->id, $origen['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/transfer', [
                'product_id'        => $product->id,
                'warehouse_from_id' => $origen['warehouse']->id,
                'warehouse_to_id'   => $destino['warehouse']->id,
                'location_from_id'  => $origen['location']->id,
                'location_to_id'    => $destino['location']->id,
                'quantity'          => 70,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $movementIds = collect($response->json('data'))->pluck('id')->all();
        $this->confirmMovements($movementIds);

        $batch->refresh();
        $this->assertSame(100, $batch->quantity_available);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $batch->id,
            'location_id' => $origen['location']->id,
            'quantity'    => 0,
        ]);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $batch->id,
            'location_id' => $locationB->id,
            'quantity'    => 30,
        ]);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $batch->id,
            'location_id' => $destino['location']->id,
            'quantity'    => 70,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $origen['warehouse']->id,
            'available_quantity' => 30,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $destino['warehouse']->id,
            'available_quantity' => 70,
        ]);
    }

    /**
     * Verifica que cuando FEFO selecciona múltiples lotes para cubrir la cantidad
     * requerida por un componente, se crea un StockMovement independiente por
     * cada lote consumido, garantizando trazabilidad a nivel de lote.
     *
     * Escenario: 2 kits KIT-CIR-BAS (5 gasas/kit → 10 gasas totales).
     * Gasa tiene dos lotes: LOT-GASA-1 con 4 und. (vence antes) y
     * LOT-GASA-2 con 10 und. FEFO tomará 4 del primero y 6 del segundo,
     * generando 2 movimientos para gasa y 1 para aguja.
     */
    public function test_salida_kit_multi_lote_crea_un_movimiento_por_lote(): void
    {
        $setup = $this->createWarehouseSetup();
        $kit   = ProductModel::where('code', 'KIT-CIR-BAS')->first();
        $gasa  = ProductModel::where('code', 'GAS-10X10')->first();
        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        // Gasa: dos lotes (FEFO tomará primero el que vence antes).
        $gasaLot1 = $this->createBatch($gasa->id, 'LOT-GASA-1', now()->addDays(10)->format('Y-m-d'), 4, $setup['location'], $setup['warehouse']->id);
        $gasaLot2 = $this->createBatch($gasa->id, 'LOT-GASA-2', now()->addDays(60)->format('Y-m-d'), 10, $setup['location'], $setup['warehouse']->id);
        $agujaLot = $this->createBatch($aguja->id, 'LOT-AGUJA', now()->addMonths(3)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($gasa->id, $setup['warehouse']->id);
        $this->recalculateStock($aguja->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $kit->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 2,
                'reason'         => 'Cirugía multi-lote',
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kit_transaction_id', fn ($id) => $id > 0);

        $this->assertSame(1, KitTransactionModel::count());

        // Gasa: debe haber dos movimientos (uno por cada lote consumido).
        $gasaMovements = StockMovementModel::where('product_id', $gasa->id)
            ->where('movement_type', 'exit')
            ->get();

        $this->assertCount(2, $gasaMovements);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $gasa->id,
            'batch_id'       => $gasaLot1->id,
            'quantity'        => -4,
            'reference_type' => 'kit_transaction',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $gasa->id,
            'batch_id'       => $gasaLot2->id,
            'quantity'        => -6,
            'reference_type' => 'kit_transaction',
        ]);

        // Aguja: un único lote, un único movimiento.
        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $aguja->id,
            'batch_id'       => $agujaLot->id,
            'quantity'        => -20,
            'reference_type' => 'kit_transaction',
        ]);

        // Estado final de los lotes.
        $gasaLot1->refresh();
        $gasaLot2->refresh();
        $agujaLot->refresh();

        $this->assertSame(0, $gasaLot1->quantity_available);
        $this->assertSame('depleted', $gasaLot1->status);
        $this->assertSame(4, $gasaLot2->quantity_available);
        $this->assertSame(80, $agujaLot->quantity_available);
    }

    /**
     * Cuando los componentes no tienen stock suficiente en el almacén, la
     * respuesta debe ser 409 con un mensaje descriptivo que mencione el kit,
     * sin que se cree ningún KitTransaction ni StockMovement.
     */
    public function test_salida_kit_falla_con_mensaje_descriptivo_cuando_no_hay_stock(): void
    {
        $setup  = $this->createWarehouseSetup();
        $kit    = ProductModel::where('code', 'KIT-CIR-BAS')->first();
        $center = CostCenterModel::where('type', 'internal')->first();

        // Sin stock de componentes: KitAvailabilityService devuelve 0.
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $kit->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'quantity'       => 1,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'kit'));

        $this->assertSame(0, KitTransactionModel::count());
        $this->assertSame(0, StockMovementModel::count());
    }

    /**
     * El endpoint GET /api/v1/stock/kit-availability devuelve la cantidad de
     * kits que se pueden armar en el almacén a partir del stock vigente de sus
     * componentes.
     *
     * KIT-CIR-BAS: 5 gasas/kit y 10 agujas/kit.
     * Con 15 gasas y 30 agujas → min(15/5, 30/10) = min(3, 3) = 3 kits.
     */
    public function test_disponibilidad_kit_devuelve_cantidad_correcta(): void
    {
        $setup = $this->createWarehouseSetup();
        $kit   = ProductModel::where('code', 'KIT-CIR-BAS')->first();
        $gasa  = ProductModel::where('code', 'GAS-10X10')->first();
        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        $this->createBatch($gasa->id, 'LOT-GASA', now()->addMonths(3)->format('Y-m-d'), 15, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($aguja->id, 'LOT-AGUJA', now()->addMonths(3)->format('Y-m-d'), 30, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($gasa->id, $setup['warehouse']->id);
        $this->recalculateStock($aguja->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/stock/kit-availability?' . http_build_query([
                'kit_product_id' => $kit->id,
                'warehouse_id'   => $setup['warehouse']->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kit_product_id', $kit->id)
            ->assertJsonPath('data.warehouse_id', $setup['warehouse']->id)
            ->assertJsonPath('data.available_kits', 3);
    }

    /**
     * El endpoint devuelve 0 kits disponibles cuando no hay stock de
     * componentes en el almacén.
     */
    public function test_disponibilidad_kit_retorna_cero_sin_stock_de_componentes(): void
    {
        $setup = $this->createWarehouseSetup();
        $kit   = ProductModel::where('code', 'KIT-CIR-BAS')->first();

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/stock/kit-availability?' . http_build_query([
                'kit_product_id' => $kit->id,
                'warehouse_id'   => $setup['warehouse']->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.available_kits', 0);
    }

    /**
     * Cuando la salida de un kit deja el stock de algún componente en o por
     * debajo de su punto de reorden, debe dispararse el evento
     * StockBelowReorderPoint para ese componente, igual que en salidas simples.
     */
    public function test_salida_kit_dispara_alerta_de_reorden_en_componente(): void
    {
        $setup = $this->createWarehouseSetup();
        $kit   = ProductModel::where('code', 'KIT-CIR-BAS')->first();
        $gasa  = ProductModel::where('code', 'GAS-10X10')->first();
        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        // Gasa tiene reorder_point = 20; con 25 unidades y salida de 5 (1 kit)
        // quedará en 20, que está ≤ reorder_point → debe disparar la alerta.
        $gasa->reorder_point = 20;
        $gasa->save();

        $this->createBatch($gasa->id, 'LOT-GASA', now()->addMonths(3)->format('Y-m-d'), 25, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($aguja->id, 'LOT-AGUJA', now()->addMonths(3)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($gasa->id, $setup['warehouse']->id);
        $this->recalculateStock($aguja->id, $setup['warehouse']->id);

        Event::fake([StockBelowReorderPoint::class]);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $kit->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 1,
                'cost_center_id' => $center->id,
            ])
            ->assertStatus(201);

        Event::assertDispatched(
            StockBelowReorderPoint::class,
            fn ($e) => $e->productId === $gasa->id
                && $e->warehouseId === $setup['warehouse']->id
                && $e->currentStock === 20
                && $e->reorderPoint === 20,
        );

        // Aguja no tiene reorder_point configurado: no debe disparar alerta.
        Event::assertNotDispatched(
            StockBelowReorderPoint::class,
            fn ($e) => $e->productId === $aguja->id,
        );
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

    private function createLocation(ZoneModel $zone, string $suffix = ''): LocationModel
    {
        return LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => "Ubicación Inventario {$suffix}",
            'code'      => "U-INV-{$suffix}",
            'is_active' => true,
        ]);
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

    /**
     * Confirma un movimiento pendiente de firma vía el endpoint de confirmación.
     * Usa datos de firma mínimos válidos para los tests.
     */
    private function confirmMovement(int $movementId): void
    {
        $minimalSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/movements/{$movementId}/confirm", [
                'delivered_by' => [
                    'name'      => 'Test Entregador',
                    'document'  => '12345678',
                    'signature' => $minimalSignature,
                ],
                'received_by' => [
                    'name'      => 'Test Receptor',
                    'document'  => '87654321',
                    'signature' => $minimalSignature,
                ],
            ])
            ->assertStatus(200);
    }

    /** Confirma todos los movimientos de una lista de IDs. */
    private function confirmMovements(array $movementIds): void
    {
        foreach ($movementIds as $id) {
            $this->confirmMovement($id);
        }
    }

    public function test_cancelar_movimiento_pendiente_lo_elimina(): void
    {
        $setup   = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $center  = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'product_id'      => $product->id,
                'warehouse_id'    => $setup['warehouse']->id,
                'location_id'     => $setup['location']->id,
                'lot_number'      => 'LOT-CANCEL-001',
                'expiration_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity_base'   => 50,
            ])->assertStatus(201);

        $exit = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 10,
                'cost_center_id' => $center->id,
            ])->assertStatus(201);

        $movementId = $exit->json('data.0.id');

        $this->assertDatabaseHas('stock_movements', ['id' => $movementId, 'status' => 'pending_signature']);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/movements/{$movementId}/pending")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('stock_movements', ['id' => $movementId]);
    }

    public function test_cancelar_movimiento_confirmado_retorna_error(): void
    {
        $setup   = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $center  = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'product_id'      => $product->id,
                'warehouse_id'    => $setup['warehouse']->id,
                'location_id'     => $setup['location']->id,
                'lot_number'      => 'LOT-CANCEL-002',
                'expiration_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity_base'   => 50,
            ])->assertStatus(201);

        $exit = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'product_id'     => $product->id,
                'warehouse_id'   => $setup['warehouse']->id,
                'location_id'    => $setup['location']->id,
                'quantity'       => 5,
                'cost_center_id' => $center->id,
            ])->assertStatus(201);

        $movementId = $exit->json('data.0.id');
        $this->confirmMovement($movementId);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/movements/{$movementId}/pending")
            ->assertStatus(422);
    }
}
