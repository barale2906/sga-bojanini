<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\KitTransactionModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
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

    private function getGeneric(string $barcode): GenericProductModel
    {
        return GenericProductModel::where('barcode', $barcode)->firstOrFail();
    }

    private function getVariant(string $barcode): ProductVariantModel
    {
        $generic = $this->getGeneric($barcode);
        return ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();
    }

    public function test_registrar_entrada_actualiza_stock(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [[
                    'product_variant_id' => $variant->id,
                    'location_id'        => $setup['location']->id,
                    'lot_number'         => 'LOT-ENTRY-001',
                    'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
                    'quantity_base'      => 100,
                ]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('batches', [
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-ENTRY-001',
            'quantity_available' => 100,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 100,
        ]);
    }

    public function test_salida_fefo_toma_lote_con_vencimiento_mas_proximo(): void
    {
        $setup   = $this->createWarehouseSetup();
        $generic = $this->getGeneric('000001');
        $variant = $this->getVariant('000001');

        $batchSoon = $this->createBatch($variant->id, 'LOT-SOON', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($variant->id, 'LOT-LATER', now()->addDays(90)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $generic->id, 'quantity' => 30, 'location_id' => $setup['location']->id]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.id'));

        $batchSoon->refresh();
        $this->assertSame(20, $batchSoon->quantity_available);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'batch_id'           => $batchSoon->id,
            'movement_type'      => 'exit',
            'quantity'           => -30,
        ]);
    }

    public function test_salida_con_stock_insuficiente_retorna_error(): void
    {
        $setup   = $this->createWarehouseSetup();
        $generic = $this->getGeneric('000001');
        $variant = $this->getVariant('000001');

        $this->createBatch($variant->id, 'LOT-LOW', now()->addDays(30)->format('Y-m-d'), 10, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $generic->id, 'quantity' => 100]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_salida_kit_descuenta_componentes_y_crea_kit_transaction(): void
    {
        $setup        = $this->createWarehouseSetup();
        $kitGeneric   = $this->getGeneric('000003');
        $gasaVariant  = $this->getVariant('000002');
        $agujaVariant = $this->getVariant('000001');

        $this->createBatch($gasaVariant->id, 'LOT-GASA', now()->addMonths(3)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($agujaVariant->id, 'LOT-AGUJA', now()->addMonths(3)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($gasaVariant->id, $setup['warehouse']->id);
        $this->recalculateStock($agujaVariant->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'reason'         => 'Cirugía test',
                'items'          => [['generic_product_id' => $kitGeneric->id, 'quantity' => 2, 'location_id' => $setup['location']->id]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertSame(1, KitTransactionModel::count());

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $gasaVariant->id,
            'movement_type'      => 'exit',
            'quantity'           => -10,
            'reference_type'     => 'kit_transaction',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $agujaVariant->id,
            'movement_type'      => 'exit',
            'quantity'           => -20,
            'reference_type'     => 'kit_transaction',
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_variant_id' => $gasaVariant->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 40,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_variant_id' => $agujaVariant->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 80,
        ]);
    }

    public function test_salida_no_selecciona_lote_vencido_aunque_sea_el_mas_proximo(): void
    {
        $setup   = $this->createWarehouseSetup();
        $generic = $this->getGeneric('000001');
        $variant = $this->getVariant('000001');

        $batchExpired = $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $batchValid   = $this->createBatch($variant->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $generic->id, 'quantity' => 30, 'location_id' => $setup['location']->id]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.id'));

        $batchValid->refresh();
        $batchExpired->refresh();
        $this->assertSame(70, $batchValid->quantity_available);
        $this->assertSame(50, $batchExpired->quantity_available);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'batch_id'           => $batchValid->id,
            'movement_type'      => 'exit',
            'quantity'           => -30,
        ]);
    }

    public function test_salida_falla_si_todo_el_stock_esta_vencido(): void
    {
        $setup   = $this->createWarehouseSetup();
        $generic = $this->getGeneric('000001');
        $variant = $this->getVariant('000001');

        $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $generic->id, 'quantity' => 10]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'EXPIRED_STOCK');
    }

    /**
     * Cuando FEFO necesita tomar de dos lotes para cubrir la cantidad solicitada,
     * se crean dos StockMovements bajo el mismo documento.
     */
    public function test_salida_simple_multi_lote_crea_un_movimiento_por_lote(): void
    {
        $setup   = $this->createWarehouseSetup();
        $generic = $this->getGeneric('000001');
        $variant = $this->getVariant('000001');
        $center  = CostCenterModel::where('type', 'internal')->first();

        $lotA = $this->createBatch($variant->id, 'LOT-MULTI-A', now()->addDays(10)->format('Y-m-d'), 5, $setup['location'], $setup['warehouse']->id);
        $lotB = $this->createBatch($variant->id, 'LOT-MULTI-B', now()->addDays(60)->format('Y-m-d'), 20, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $generic->id, 'quantity' => 12, 'location_id' => $setup['location']->id]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.id'));

        $movements = StockMovementModel::where('product_variant_id', $variant->id)
            ->where('movement_type', 'exit')
            ->get();

        $this->assertCount(2, $movements);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'batch_id'           => $lotA->id,
            'quantity'           => -5,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'batch_id'           => $lotB->id,
            'quantity'           => -7,
        ]);

        $lotA->refresh();
        $lotB->refresh();

        $this->assertSame(0, $lotA->quantity_available);
        $this->assertSame('depleted', $lotA->status);
        $this->assertSame(13, $lotB->quantity_available);
    }

    /**
     * Cuando FEFO necesita tomar de dos lotes para una transferencia,
     * se crea un StockMovement por cada lote con su cantidad exacta, bajo un mismo documento.
     */
    public function test_transferencia_multi_lote_crea_un_movimiento_por_lote(): void
    {
        $origen  = $this->createWarehouseSetup('-ORIGEN-ML');
        $destino = $this->createWarehouseSetup('-DESTINO-ML');
        $variant = $this->getVariant('000001');

        $lotA = $this->createBatch($variant->id, 'LOT-TRANS-A', now()->addDays(10)->format('Y-m-d'), 8, $origen['location'], $origen['warehouse']->id);
        $lotB = $this->createBatch($variant->id, 'LOT-TRANS-B', now()->addDays(60)->format('Y-m-d'), 15, $origen['location'], $origen['warehouse']->id);

        $this->recalculateStock($variant->id, $origen['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/transfer', [
                'warehouse_from_id' => $origen['warehouse']->id,
                'warehouse_to_id'   => $destino['warehouse']->id,
                'items'             => [[
                    'product_variant_id' => $variant->id,
                    'location_from_id'   => $origen['location']->id,
                    'location_to_id'     => $destino['location']->id,
                    'quantity'           => 10,
                ]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.id'));

        $movements = StockMovementModel::where('product_variant_id', $variant->id)
            ->where('movement_type', 'transfer')
            ->get();

        $this->assertCount(2, $movements);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'batch_id'           => $lotA->id,
            'quantity'           => 8,
            'warehouse_to_id'    => $destino['warehouse']->id,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'batch_id'           => $lotB->id,
            'quantity'           => 2,
            'warehouse_to_id'    => $destino['warehouse']->id,
        ]);

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
        $origen  = $this->createWarehouseSetup('-ORIGEN');
        $destino = $this->createWarehouseSetup('-DESTINO');
        $variant = $this->getVariant('000001');

        $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $origen['location'], $origen['warehouse']->id);
        $this->recalculateStock($variant->id, $origen['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/transfer', [
                'warehouse_from_id' => $origen['warehouse']->id,
                'warehouse_to_id'   => $destino['warehouse']->id,
                'items'             => [[
                    'product_variant_id' => $variant->id,
                    'location_from_id'   => $origen['location']->id,
                    'location_to_id'     => $destino['location']->id,
                    'quantity'           => 10,
                ]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'EXPIRED_STOCK');
    }

    public function test_devolucion_permite_gestionar_lote_vencido(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $batchExpired = $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/return', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'quantity'           => 10,
                'reason'             => 'Devolución de producto vencido al proveedor',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.movement_document_id'));

        $batchExpired->refresh();
        $this->assertSame(40, $batchExpired->quantity_available);
    }

    public function test_ajuste_negativo_permite_gestionar_lote_vencido(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $batchExpired = $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/adjustment', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'quantity'           => -10,
                'reason'             => 'Ajuste por conteo físico: lote vencido',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.movement_document_id'));

        $batchExpired->refresh();
        $this->assertSame(40, $batchExpired->quantity_available);
    }

    public function test_listado_de_lotes_por_producto_filtra_disponibles_para_salida(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $batchExpired = $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $batchValid   = $this->createBatch($variant->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/v1/products/{$variant->id}/batches?available_for_exit=1")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($batchValid->id));
        $this->assertFalse($ids->contains($batchExpired->id));
    }

    public function test_stock_summary_excluye_lote_vencido(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $this->assertDatabaseHas('stock_summaries', [
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'total_quantity'     => 0,
            'available_quantity' => 0,
        ]);
    }

    public function test_stock_summary_incluye_expired_quantity(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $this->createBatch($variant->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 30, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/stock/summary?'.http_build_query([
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.available_quantity', 30)
            ->assertJsonPath('data.expired_quantity', 50);
    }

    public function test_baja_descuenta_stock_vigente_por_dano(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $batch = $this->createBatch($variant->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'batch_id'           => $batch->id,
                'quantity'           => 5,
                'reason'             => 'Producto dañado: caja golpeada',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.movement_type', 'loss')
            ->assertJsonPath('data.batch_id', $batch->id)
            ->assertJsonPath('data.quantity', -5);

        $this->confirmDocument($response->json('data.movement_document_id'));

        $batch->refresh();
        $this->assertSame(45, $batch->quantity_available);
        $this->assertSame(45, $batch->locations()->first()->pivot->quantity);
    }

    public function test_baja_permite_gestionar_lote_vencido(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $batchExpired = $this->createBatch($variant->id, 'LOT-EXPIRED', now()->subDay()->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'batch_id'           => $batchExpired->id,
                'quantity'           => 10,
                'reason'             => 'Producto vencido',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.movement_document_id'));

        $batchExpired->refresh();
        $this->assertSame(40, $batchExpired->quantity_available);
    }

    public function test_baja_con_stock_insuficiente_retorna_error(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $batch = $this->createBatch($variant->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 5, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'batch_id'           => $batch->id,
                'quantity'           => 10,
                'reason'             => 'Pérdida',
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $batch->refresh();
        $this->assertSame(5, $batch->quantity_available);
    }

    public function test_baja_permite_elegir_lote_especifico_sin_aplicar_fefo(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $batchExpiresFirst = $this->createBatch($variant->id, 'LOT-FEFO-1', now()->addDays(10)->format('Y-m-d'), 30, $setup['location'], $setup['warehouse']->id);
        $batchExpiresLater = $this->createBatch($variant->id, 'LOT-FEFO-2', now()->addDays(60)->format('Y-m-d'), 30, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'batch_id'           => $batchExpiresLater->id,
                'quantity'           => 10,
                'reason'             => 'Producto dañado',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.batch_id', $batchExpiresLater->id);

        $this->confirmDocument($response->json('data.movement_document_id'));

        $batchExpiresFirst->refresh();
        $batchExpiresLater->refresh();

        $this->assertSame(30, $batchExpiresFirst->quantity_available);
        $this->assertSame(20, $batchExpiresLater->quantity_available);
    }

    public function test_baja_valida_cantidad_contra_la_ubicacion_del_lote(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $otherLocation = LocationModel::create([
            'zone_id'   => $setup['zone']->id,
            'name'      => 'Ubicación Inventario 2',
            'code'      => 'U-INV2',
            'is_active' => true,
        ]);

        $batch = BatchModel::create([
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-SPLIT',
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => 20,
            'quantity_available' => 20,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $batch->locations()->attach($setup['location']->id, ['quantity' => 5]);
        $batch->locations()->attach($otherLocation->id, ['quantity' => 15]);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'batch_id'           => $batch->id,
                'quantity'           => 10,
                'reason'             => 'Pérdida',
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $batch->refresh();
        $this->assertSame(20, $batch->quantity_available);
    }

    public function test_baja_requiere_batch_id(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $this->createBatch($variant->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'quantity'           => 5,
                'reason'             => 'Producto dañado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['batch_id']);
    }

    public function test_baja_rechaza_lote_de_otro_producto(): void
    {
        $setup        = $this->createWarehouseSetup();
        $agujaVariant = $this->getVariant('000001');
        $gasaVariant  = $this->getVariant('000002');

        $this->createBatch($agujaVariant->id, 'LOT-VALID', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $otherBatch = $this->createBatch($gasaVariant->id, 'LOT-OTHER', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($agujaVariant->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/loss', [
                'product_variant_id' => $agujaVariant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'batch_id'           => $otherBatch->id,
                'quantity'           => 5,
                'reason'             => 'Producto dañado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['batch_id']);
    }

    public function test_devolucion_sin_ubicacion_actualiza_resumen_de_stock(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $batch = $this->createBatch($variant->id, 'LOT-RETURN', now()->addDays(30)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/return', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'quantity'           => 10,
                'reason'             => 'Devolución sin ubicación específica',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.movement_document_id'));

        $batch->refresh();
        $this->assertSame(40, $batch->quantity_available);

        $this->assertDatabaseHas('batch_location', [
            'batch_id'    => $batch->id,
            'location_id' => $setup['location']->id,
            'quantity'    => 40,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 40,
        ]);
    }

    public function test_ajuste_negativo_reparte_descuento_entre_ubicaciones_sin_dejar_negativos(): void
    {
        $setup     = $this->createWarehouseSetup();
        $variant   = $this->getVariant('000001');
        $locationB = $this->createLocation($setup['zone'], 'B');

        $batch = BatchModel::create([
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-SPLIT',
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => 100,
            'quantity_available' => 100,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $batch->locations()->attach($setup['location']->id, ['quantity' => 60]);
        $batch->locations()->attach($locationB->id, ['quantity' => 40]);

        $this->recalculateStock($variant->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/adjustment', [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $setup['warehouse']->id,
                'location_id'        => $setup['location']->id,
                'quantity'           => -70,
                'reason'             => 'Conteo físico: faltante repartido entre ubicaciones',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.movement_document_id'));

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
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 30,
        ]);
    }

    public function test_transferencia_reparte_descuento_entre_ubicaciones_sin_dejar_negativos(): void
    {
        $origen    = $this->createWarehouseSetup('-ORIGEN-MULTI');
        $destino   = $this->createWarehouseSetup('-DESTINO-MULTI');
        $variant   = $this->getVariant('000001');
        $locationB = $this->createLocation($origen['zone'], 'B-ORIGEN-MULTI');

        $batch = BatchModel::create([
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-SPLIT-TRANSFER',
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => 100,
            'quantity_available' => 100,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $batch->locations()->attach($origen['location']->id, ['quantity' => 60]);
        $batch->locations()->attach($locationB->id, ['quantity' => 40]);

        $this->recalculateStock($variant->id, $origen['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/transfer', [
                'warehouse_from_id' => $origen['warehouse']->id,
                'warehouse_to_id'   => $destino['warehouse']->id,
                'items'             => [[
                    'product_variant_id' => $variant->id,
                    'location_from_id'   => $origen['location']->id,
                    'location_to_id'     => $destino['location']->id,
                    'quantity'           => 70,
                ]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->confirmDocument($response->json('data.id'));

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
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $origen['warehouse']->id,
            'available_quantity' => 30,
        ]);

        $this->assertDatabaseHas('stock_summaries', [
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $destino['warehouse']->id,
            'available_quantity' => 70,
        ]);
    }

    /**
     * Escenario: 2 kits (5 gasas/kit → 10 gasas totales).
     * Gasa: LOT-GASA-1 con 4 und. (vence antes) y LOT-GASA-2 con 10 und.
     * FEFO tomará 4 del primero y 6 del segundo → 2 movimientos para gasa, 1 para aguja.
     */
    public function test_salida_kit_multi_lote_crea_un_movimiento_por_lote(): void
    {
        $setup        = $this->createWarehouseSetup();
        $kitGeneric   = $this->getGeneric('000003');
        $gasaVariant  = $this->getVariant('000002');
        $agujaVariant = $this->getVariant('000001');

        $gasaLot1 = $this->createBatch($gasaVariant->id, 'LOT-GASA-1', now()->addDays(10)->format('Y-m-d'), 4, $setup['location'], $setup['warehouse']->id);
        $gasaLot2 = $this->createBatch($gasaVariant->id, 'LOT-GASA-2', now()->addDays(60)->format('Y-m-d'), 10, $setup['location'], $setup['warehouse']->id);
        $agujaLot = $this->createBatch($agujaVariant->id, 'LOT-AGUJA', now()->addMonths(3)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($gasaVariant->id, $setup['warehouse']->id);
        $this->recalculateStock($agujaVariant->id, $setup['warehouse']->id);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'reason'         => 'Cirugía multi-lote',
                'items'          => [['generic_product_id' => $kitGeneric->id, 'quantity' => 2, 'location_id' => $setup['location']->id]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertSame(1, KitTransactionModel::count());

        $gasaMovements = StockMovementModel::where('product_variant_id', $gasaVariant->id)
            ->where('movement_type', 'exit')
            ->get();

        $this->assertCount(2, $gasaMovements);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $gasaVariant->id,
            'batch_id'           => $gasaLot1->id,
            'quantity'           => -4,
            'reference_type'     => 'kit_transaction',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $gasaVariant->id,
            'batch_id'           => $gasaLot2->id,
            'quantity'           => -6,
            'reference_type'     => 'kit_transaction',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $agujaVariant->id,
            'batch_id'           => $agujaLot->id,
            'quantity'           => -20,
            'reference_type'     => 'kit_transaction',
        ]);

        $gasaLot1->refresh();
        $gasaLot2->refresh();
        $agujaLot->refresh();

        $this->assertSame(0, $gasaLot1->quantity_available);
        $this->assertSame('depleted', $gasaLot1->status);
        $this->assertSame(4, $gasaLot2->quantity_available);
        $this->assertSame(80, $agujaLot->quantity_available);
    }

    /**
     * Sin stock de componentes → 409 con mensaje que menciona el kit,
     * sin KitTransaction ni StockMovement creados.
     */
    public function test_salida_kit_falla_con_mensaje_descriptivo_cuando_no_hay_stock(): void
    {
        $setup      = $this->createWarehouseSetup();
        $kitGeneric = $this->getGeneric('000003');
        $center     = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $kitGeneric->id, 'quantity' => 1]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'kit'));

        $this->assertSame(0, KitTransactionModel::count());
        $this->assertSame(0, StockMovementModel::count());
    }

    /**
     * KIT-CIR-BAS: 5 gasas/kit y 10 agujas/kit.
     * Con 15 gasas y 30 agujas → min(15/5, 30/10) = 3 kits.
     */
    public function test_disponibilidad_kit_devuelve_cantidad_correcta(): void
    {
        $setup        = $this->createWarehouseSetup();
        $kitGeneric   = $this->getGeneric('000003');
        $gasaVariant  = $this->getVariant('000002');
        $agujaVariant = $this->getVariant('000001');

        $this->createBatch($gasaVariant->id, 'LOT-GASA', now()->addMonths(3)->format('Y-m-d'), 15, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($agujaVariant->id, 'LOT-AGUJA', now()->addMonths(3)->format('Y-m-d'), 30, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($gasaVariant->id, $setup['warehouse']->id);
        $this->recalculateStock($agujaVariant->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/stock/kit-availability?' . http_build_query([
                'kit_generic_id' => $kitGeneric->id,
                'warehouse_id'   => $setup['warehouse']->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.generic_product_id', $kitGeneric->id)
            ->assertJsonPath('data.warehouse_id', $setup['warehouse']->id)
            ->assertJsonPath('data.available_kits', 3);
    }

    public function test_disponibilidad_kit_retorna_cero_sin_stock_de_componentes(): void
    {
        $setup      = $this->createWarehouseSetup();
        $kitGeneric = $this->getGeneric('000003');

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/stock/kit-availability?' . http_build_query([
                'kit_generic_id' => $kitGeneric->id,
                'warehouse_id'   => $setup['warehouse']->id,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.available_kits', 0);
    }

    /**
     * Salida de kit que deja stock ≤ reorder_point del componente
     * dispara StockBelowReorderPoint para ese genérico.
     */
    public function test_salida_kit_dispara_alerta_de_reorden_en_componente(): void
    {
        $setup        = $this->createWarehouseSetup();
        $kitGeneric   = $this->getGeneric('000003');
        $gasaGeneric  = $this->getGeneric('000002');
        $gasaVariant  = $this->getVariant('000002');
        $agujaGeneric = $this->getGeneric('000001');
        $agujaVariant = $this->getVariant('000001');

        $gasaGeneric->update(['reorder_point' => 20]);
        $agujaGeneric->update(['reorder_point' => 50]);

        $this->createBatch($gasaVariant->id, 'LOT-GASA', now()->addMonths(3)->format('Y-m-d'), 25, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($agujaVariant->id, 'LOT-AGUJA', now()->addMonths(3)->format('Y-m-d'), 100, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($gasaVariant->id, $setup['warehouse']->id);
        $this->recalculateStock($agujaVariant->id, $setup['warehouse']->id);

        Event::fake([StockBelowReorderPoint::class]);

        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $kitGeneric->id, 'quantity' => 1, 'location_id' => $setup['location']->id]],
            ])
            ->assertStatus(201);

        Event::assertDispatched(
            StockBelowReorderPoint::class,
            fn ($e) => $e->genericProductId === $gasaGeneric->id
                && $e->warehouseId === $setup['warehouse']->id
                && $e->currentStock === 20
                && $e->reorderPoint === 20,
        );

        Event::assertNotDispatched(
            StockBelowReorderPoint::class,
            fn ($e) => $e->genericProductId === $agujaGeneric->id,
        );
    }

    public function test_salida_multi_producto_agrupa_en_un_solo_documento(): void
    {
        $setup        = $this->createWarehouseSetup();
        $generic1     = $this->getGeneric('000001');
        $generic2     = $this->getGeneric('000002');
        $variant1     = $this->getVariant('000001');
        $variant2     = $this->getVariant('000002');
        $center       = CostCenterModel::where('type', 'internal')->first();

        $this->createBatch($variant1->id, 'LOT-P1', now()->addMonths(3)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->createBatch($variant2->id, 'LOT-P2', now()->addMonths(3)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);

        $this->recalculateStock($variant1->id, $setup['warehouse']->id);
        $this->recalculateStock($variant2->id, $setup['warehouse']->id);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [
                    ['generic_product_id' => $generic1->id, 'quantity' => 10],
                    ['generic_product_id' => $generic2->id, 'quantity' => 5],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $documentId = $response->json('data.id');
        $documentNumber = $response->json('data.document_number');

        $this->assertNotNull($documentId);
        $this->assertStringStartsWith('SAL-', $documentNumber);

        $document = MovementDocumentModel::with('movements')->find($documentId);

        $this->assertCount(2, $document->movements);

        $movDoc = $this->withHeaders($this->auth())
            ->getJson("/api/v1/movement-documents/{$documentId}")
            ->assertStatus(200)
            ->assertJsonPath('data.document_number', $documentNumber);

        $this->assertCount(2, $movDoc->json('data.movements'));
    }

    public function test_comprobante_de_entrada_genera_numero_consecutivo(): void
    {
        $setup   = $this->createWarehouseSetup();
        $variant = $this->getVariant('000001');

        $r1 = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [[
                    'product_variant_id' => $variant->id,
                    'location_id'        => $setup['location']->id,
                    'lot_number'         => 'LOT-SEQ-1',
                    'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
                    'quantity_base'      => 10,
                ]],
            ])
            ->assertStatus(201);

        $r2 = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [[
                    'product_variant_id' => $variant->id,
                    'location_id'        => $setup['location']->id,
                    'lot_number'         => 'LOT-SEQ-2',
                    'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
                    'quantity_base'      => 20,
                ]],
            ])
            ->assertStatus(201);

        $num1 = $r1->json('data.document_number');
        $num2 = $r2->json('data.document_number');

        $this->assertStringStartsWith('ENT-', $num1);
        $this->assertStringStartsWith('ENT-', $num2);
        $this->assertNotSame($num1, $num2);
    }

    public function test_cancelar_documento_pendiente_lo_elimina(): void
    {
        $setup   = $this->createWarehouseSetup();
        $generic = $this->getGeneric('000001');
        $variant = $this->getVariant('000001');
        $center  = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [[
                    'product_variant_id' => $variant->id,
                    'location_id'        => $setup['location']->id,
                    'lot_number'         => 'LOT-CANCEL-001',
                    'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
                    'quantity_base'      => 50,
                ]],
            ])->assertStatus(201);

        $exit = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $generic->id, 'quantity' => 10, 'location_id' => $setup['location']->id]],
            ])->assertStatus(201);

        $documentId = $exit->json('data.id');

        $this->assertDatabaseHas('movement_documents', ['id' => $documentId, 'status' => 'pending_signature']);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/movement-documents/{$documentId}/pending")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('movement_documents', ['id' => $documentId]);
        $this->assertDatabaseMissing('stock_movements', ['movement_document_id' => $documentId]);
    }

    public function test_cancelar_documento_confirmado_retorna_error(): void
    {
        $setup   = $this->createWarehouseSetup();
        $generic = $this->getGeneric('000001');
        $variant = $this->getVariant('000001');
        $center  = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [[
                    'product_variant_id' => $variant->id,
                    'location_id'        => $setup['location']->id,
                    'lot_number'         => 'LOT-CANCEL-002',
                    'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
                    'quantity_base'      => 50,
                ]],
            ])->assertStatus(201);

        $exit = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $generic->id, 'quantity' => 5, 'location_id' => $setup['location']->id]],
            ])->assertStatus(201);

        $documentId = $exit->json('data.id');
        $this->confirmDocument($documentId);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/movement-documents/{$documentId}/pending")
            ->assertStatus(422);
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

    private function createBatch(int $variantId, string $lotNumber, string $expirationDate, int $quantity, LocationModel $location, int $warehouseId): BatchModel
    {
        $batch = BatchModel::create([
            'product_variant_id' => $variantId,
            'lot_number'         => $lotNumber,
            'expiration_date'    => $expirationDate,
            'quantity_received'  => $quantity,
            'quantity_available' => $quantity,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batch->locations()->attach($location->id, ['quantity' => $quantity]);

        $document = MovementDocumentModel::create([
            'document_number' => 'ENT-TEST-' . uniqid(),
            'document_type'   => 'entry',
            'warehouse_id'    => $warehouseId,
            'user_id'         => UserModel::first()->id,
            'status'          => 'confirmed',
        ]);

        StockMovementModel::create([
            'movement_document_id' => $document->id,
            'warehouse_id'         => $warehouseId,
            'product_variant_id'   => $variantId,
            'batch_id'             => $batch->id,
            'location_to_id'       => $location->id,
            'movement_type'        => 'entry',
            'quantity'             => $quantity,
            'user_id'              => UserModel::first()->id,
        ]);

        return $batch;
    }

    private function recalculateStock(int $variantId, int $warehouseId): void
    {
        app(\App\Modules\Inventory\Domain\Services\StockCalculator::class)
            ->recalculateSummary($variantId, $warehouseId);
    }

    private function confirmDocument(int $documentId): void
    {
        $minimalSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/movement-documents/{$documentId}/confirm", [
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
}
