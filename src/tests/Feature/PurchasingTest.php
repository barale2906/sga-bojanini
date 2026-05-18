<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);

        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_ciclo_completo_orden_compra_con_recepcion_parcial_y_total(): void
    {
        $setup = $this->createWarehouseSetup();
        $supplier = SupplierModel::first();
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $presentation = ProductPresentationModel::where('code', 'PQ-100')->first();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/purchase-orders', [
                'supplier_id'  => $supplier->id,
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [
                    [
                        'product_id'              => $product->id,
                        'product_presentation_id' => $presentation->id,
                        'quantity'                => 2,
                        'unit_price'              => 50000,
                    ],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');

        $orderId = $create->json('data.id');
        $itemId = $create->json('data.items.0.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/purchase-orders/{$orderId}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_approval');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/purchase-orders/{$orderId}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/purchase-orders/{$orderId}/send")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'sent');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/purchase-orders/{$orderId}/receive", [
                'items' => [
                    [
                        'item_id'           => $itemId,
                        'quantity_received' => 1,
                        'lot_number'        => 'LOT-PO-001',
                        'expiration_date'   => now()->addMonths(12)->format('Y-m-d'),
                        'location_id'       => $setup['location']->id,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'partially_received');

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 100,
        ]);

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/purchase-orders/{$orderId}/receive", [
                'items' => [
                    [
                        'item_id'           => $itemId,
                        'quantity_received' => 1,
                        'lot_number'        => 'LOT-PO-002',
                        'expiration_date'   => now()->addMonths(12)->format('Y-m-d'),
                        'location_id'       => $setup['location']->id,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'received');

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 200,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'id'                     => $itemId,
            'quantity_received'      => 2,
            'quantity_received_base' => 200,
        ]);
    }

    public function test_rechazo_orden_compra(): void
    {
        $setup = $this->createWarehouseSetup();
        $supplier = SupplierModel::first();
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $presentation = ProductPresentationModel::where('code', 'PQ-100')->first();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/purchase-orders', [
                'supplier_id'  => $supplier->id,
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [
                    [
                        'product_id'              => $product->id,
                        'product_presentation_id' => $presentation->id,
                        'quantity'                => 1,
                        'unit_price'              => 10000,
                    ],
                ],
            ])
            ->assertStatus(201);

        $orderId = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/purchase-orders/{$orderId}/submit")
            ->assertStatus(200);

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/purchase-orders/{$orderId}/reject", [
                'comments' => 'Presupuesto insuficiente',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('purchase_orders', [
            'id'     => $orderId,
            'status' => 'rejected',
        ]);
    }

    public function test_sugerencias_reorden(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();
        $product->update([
            'reorder_point' => 100,
            'min_stock'     => 50,
        ]);

        StockMovementModel::create([
            'warehouse_id'  => $setup['warehouse']->id,
            'product_id'    => $product->id,
            'movement_type' => 'exit',
            'quantity'      => -900,
            'user_id'       => UserModel::first()->id,
        ]);

        StockSummaryModel::create([
            'warehouse_id'       => $setup['warehouse']->id,
            'product_id'         => $product->id,
            'total_quantity'     => 30,
            'reserved_quantity'  => 0,
            'available_quantity' => 30,
            'last_movement_at'   => now(),
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/purchase-orders/suggestions')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $codes = collect($response->json('data'))->pluck('product_code');
        $this->assertTrue($codes->contains('AGU-21G'));
    }

    public function test_no_permite_producto_kit_en_orden_compra(): void
    {
        $setup = $this->createWarehouseSetup();
        $supplier = SupplierModel::first();
        $kit = ProductModel::where('code', 'KIT-CIR-BAS')->first();
        $kitUnit = UnitOfMeasureModel::where('abbreviation', 'KIT')->first();

        $presentation = ProductPresentationModel::create([
            'product_id'          => $kit->id,
            'code'                => 'KIT-UNIT-TEST',
            'name'                => 'Kit unidad test',
            'units_of_measure_id' => $kitUnit->id,
            'factor_to_base'      => 1,
            'level'               => 1,
            'sort_order'          => 1,
        ]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/purchase-orders', [
                'supplier_id'  => $supplier->id,
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [
                    [
                        'product_id'              => $kit->id,
                        'product_presentation_id' => $presentation->id,
                        'quantity'                => 1,
                        'unit_price'              => 10000,
                    ],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /**
     * @return array{warehouse: WarehouseModel, zone: ZoneModel, location: LocationModel}
     */
    private function createWarehouseSetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => 'Almacén Compras Test',
            'code'      => 'ALM-PO',
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => 'Zona Compras',
            'code'         => 'Z-PO',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $location = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => 'Ubicación Compras',
            'code'      => 'U-PO',
            'is_active' => true,
        ]);

        return compact('warehouse', 'zone', 'location');
    }
}
