<?php

namespace Tests\Feature\Integration;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flujo end-to-end: crear OC → aprobar → enviar → recibir → stock actualizado.
 */
class CompletePurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->auth = $this->bearerAuthFor($admin);
    }

    public function test_complete_purchase_flow(): void
    {
        $setup = $this->createWarehouseSetup();
        $supplier = SupplierModel::firstOrFail();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();
        $presentation = ProductPresentationModel::where('code', 'PQ-100')->firstOrFail();

        $create = $this->withHeaders($this->auth)
            ->postJson('/api/v1/purchase-orders', [
                'supplier_id'  => $supplier->id,
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [
                    [
                        'product_id'              => $product->id,
                        'product_presentation_id' => $presentation->id,
                        'quantity'                => 1,
                        'unit_price'              => 50000,
                    ],
                ],
            ])
            ->assertStatus(201);

        $orderId = $create->json('data.id');
        $itemId = $create->json('data.items.0.id');

        $this->withHeaders($this->auth)
            ->postJson("/api/v1/purchase-orders/{$orderId}/submit")
            ->assertOk();

        $this->withHeaders($this->auth)
            ->postJson("/api/v1/purchase-orders/{$orderId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->withHeaders($this->auth)
            ->postJson("/api/v1/purchase-orders/{$orderId}/send")
            ->assertOk();

        $this->withHeaders($this->auth)
            ->postJson("/api/v1/purchase-orders/{$orderId}/receive", [
                'items' => [
                    [
                        'item_id'           => $itemId,
                        'quantity_received' => 1,
                        'lot_number'        => 'LOTE-E2E-001',
                        'expiration_date'   => now()->addYear()->format('Y-m-d'),
                        'location_id'       => $setup['location']->id,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 100,
        ]);
    }

  /**
     * @return array{warehouse: WarehouseModel, zone: ZoneModel, location: LocationModel}
     */
    private function createWarehouseSetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name' => 'Almacén E2E', 'code' => 'ALM-E2E', 'is_active' => true,
        ]);
        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id, 'name' => 'Zona', 'code' => 'Z-E2E',
            'type' => 'ambient', 'is_active' => true,
        ]);
        $location = LocationModel::create([
            'zone_id' => $zone->id, 'name' => 'Ubicación', 'code' => 'U-E2E', 'is_active' => true,
        ]);

        return compact('warehouse', 'zone', 'location');
    }
}
