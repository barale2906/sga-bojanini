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

        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->first();
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

    /**
     * @return array{warehouse: WarehouseModel, zone: ZoneModel, location: LocationModel}
     */
    private function createWarehouseSetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => 'Almacén Inventario Test',
            'code'      => 'ALM-INV',
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => 'Zona Inventario',
            'code'         => 'Z-INV',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $location = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => 'Ubicación Inventario',
            'code'      => 'U-INV',
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
