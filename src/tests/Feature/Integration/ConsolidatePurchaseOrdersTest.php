<?php

namespace Tests\Feature\Integration;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatePurchaseOrdersTest extends TestCase
{
    use RefreshDatabase;

    private array $auth;
    private SupplierModel $supplier;
    private ProductVariantModel $variant;
    private ProductPresentationModel $presentation;
    private WarehouseModel $warehouse;
    private LocationModel $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->auth  = $this->bearerAuthFor($admin);

        $this->supplier     = SupplierModel::firstOrFail();
        $generic            = GenericProductModel::where('barcode', '000001')->firstOrFail();
        $this->variant      = ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();
        $this->presentation = ProductPresentationModel::where('code', 'PQ-100')->firstOrFail();

        $this->warehouse = WarehouseModel::create([
            'name' => 'Almacén Consolidación', 'code' => 'ALM-CONS', 'is_active' => true,
        ]);
        $zone = ZoneModel::create([
            'warehouse_id' => $this->warehouse->id, 'name' => 'Zona', 'code' => 'Z-CONS',
            'type' => 'ambient', 'is_active' => true,
        ]);
        $this->location = LocationModel::create([
            'zone_id' => $zone->id, 'name' => 'Ubicación', 'code' => 'U-CONS', 'is_active' => true,
        ]);
    }

    public function test_consolidate_two_received_orders(): void
    {
        $id1 = $this->createReceivedOrder('OC-TEST-00001', 5, 10000);
        $id2 = $this->createReceivedOrder('OC-TEST-00002', 3, 10000);

        $response = $this->withHeaders($this->auth)
            ->postJson('/api/v1/consolidated-orders', [
                'purchase_order_ids' => [$id1, $id2],
                'notes'              => 'Consolidado agosto 2026',
            ])
            ->assertStatus(201);

        $response->assertJsonPath('data.supplier_id', $this->supplier->id);
        $this->assertStringStartsWith('OCC-', $response->json('data.code'));

        // La OC consolidada agrupa por variant+presentation+price → una sola línea con cantidad 8
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('8.000', $items[0]['quantity']);

        // Las OC originales quedan bloqueadas
        $this->assertDatabaseHas('purchase_orders', ['id' => $id1, 'consolidated_order_id' => $response->json('data.id')]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $id2, 'consolidated_order_id' => $response->json('data.id')]);
    }

    public function test_same_product_different_prices_generates_two_lines(): void
    {
        $id1 = $this->createReceivedOrder('OC-TEST-00003', 2, 10000);
        $id2 = $this->createReceivedOrder('OC-TEST-00004', 4, 15000);

        $response = $this->withHeaders($this->auth)
            ->postJson('/api/v1/consolidated-orders', [
                'purchase_order_ids' => [$id1, $id2],
            ])
            ->assertStatus(201);

        $items = $response->json('data.items');
        $this->assertCount(2, $items);
    }

    public function test_cannot_consolidate_already_consolidated_order(): void
    {
        $id1 = $this->createReceivedOrder('OC-TEST-00005', 2, 10000);
        $id2 = $this->createReceivedOrder('OC-TEST-00006', 3, 10000);

        $this->withHeaders($this->auth)
            ->postJson('/api/v1/consolidated-orders', ['purchase_order_ids' => [$id1]])
            ->assertStatus(201);

        $this->withHeaders($this->auth)
            ->postJson('/api/v1/consolidated-orders', ['purchase_order_ids' => [$id1, $id2]])
            ->assertStatus(409);
    }

    public function test_cannot_consolidate_non_received_order(): void
    {
        $draft = $this->withHeaders($this->auth)
            ->postJson('/api/v1/purchase-orders', $this->orderPayload(2))
            ->assertStatus(201)
            ->json('data.id');

        $this->withHeaders($this->auth)
            ->postJson('/api/v1/consolidated-orders', ['purchase_order_ids' => [$draft]])
            ->assertStatus(409);
    }

    public function test_cannot_consolidate_orders_from_different_suppliers(): void
    {
        $otherSupplier = \App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel::create([
            'name'      => 'Otro Proveedor',
            'is_active' => true,
        ]);

        $id1 = $this->createReceivedOrder('OC-TEST-00007', 2, 10000);

        $orderWithOtherSupplier = PurchaseOrderModel::create([
            'supplier_id'  => $otherSupplier->id,
            'warehouse_id' => $this->warehouse->id,
            'code'         => 'OC-TEST-00008',
            'status'       => 'received',
            'subtotal'     => 10000,
            'tax_amount'   => 0,
            'total_amount' => 10000,
            'created_by'   => 1,
            'received_at'  => now(),
        ]);

        $this->withHeaders($this->auth)
            ->postJson('/api/v1/consolidated-orders', [
                'purchase_order_ids' => [$id1, $orderWithOtherSupplier->id],
            ])
            ->assertStatus(409);
    }

    public function test_consolidable_endpoint_returns_pending_orders_outside_date_range(): void
    {
        $oldId = $this->createReceivedOrder('OC-TEST-00009', 1, 5000);

        // Forzamos fecha antigua en la OC
        PurchaseOrderModel::where('id', $oldId)->update(['created_at' => now()->subMonths(2)]);

        $recentId = $this->createReceivedOrder('OC-TEST-00010', 1, 5000);

        $response = $this->withHeaders($this->auth)
            ->getJson('/api/v1/purchase-orders/consolidable?date_from='.now()->subDays(5)->format('Y-m-d').'&date_to='.now()->format('Y-m-d'))
            ->assertOk();

        $inRange = collect($response->json('data.in_range'))->pluck('id');
        $pending = collect($response->json('data.pending'))->pluck('id');

        $this->assertContains($recentId, $inRange->toArray());
        $this->assertContains($oldId, $pending->toArray());
    }

    public function test_show_consolidated_order_includes_source_orders(): void
    {
        $id1 = $this->createReceivedOrder('OC-TEST-00011', 2, 8000);
        $id2 = $this->createReceivedOrder('OC-TEST-00012', 1, 8000);

        $consolidatedId = $this->withHeaders($this->auth)
            ->postJson('/api/v1/consolidated-orders', ['purchase_order_ids' => [$id1, $id2]])
            ->assertStatus(201)
            ->json('data.id');

        $response = $this->withHeaders($this->auth)
            ->getJson("/api/v1/consolidated-orders/{$consolidatedId}")
            ->assertOk();

        $sourceCodes = collect($response->json('data.purchase_orders'))->pluck('id');
        $this->assertContains($id1, $sourceCodes->toArray());
        $this->assertContains($id2, $sourceCodes->toArray());
    }

    // ─────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────

    private function createReceivedOrder(string $code, int $qty, float $unitPrice): int
    {
        $orderId = $this->withHeaders($this->auth)
            ->postJson('/api/v1/purchase-orders', $this->orderPayload($qty, $unitPrice))
            ->assertStatus(201)
            ->json('data.id');

        $itemId = PurchaseOrderModel::findOrFail($orderId)->items->first()->id;

        $this->withHeaders($this->auth)->postJson("/api/v1/purchase-orders/{$orderId}/submit")->assertOk();
        $this->withHeaders($this->auth)->postJson("/api/v1/purchase-orders/{$orderId}/approve")->assertOk();
        $this->withHeaders($this->auth)->postJson("/api/v1/purchase-orders/{$orderId}/send")->assertOk();

        $this->withHeaders($this->auth)
            ->postJson("/api/v1/purchase-orders/{$orderId}/receive", [
                'items' => [[
                    'item_id'           => $itemId,
                    'quantity_received' => $qty,
                    'lot_number'        => "LOTE-CONS-{$orderId}",
                    'expiration_date'   => now()->addYear()->format('Y-m-d'),
                    'location_id'       => $this->location->id,
                ]],
            ])
            ->assertOk();

        return $orderId;
    }

    private function orderPayload(int $qty = 10, float $unitPrice = 10000): array
    {
        return [
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'items'        => [[
                'product_variant_id'      => $this->variant->id,
                'product_presentation_id' => $this->presentation->id,
                'quantity'                => $qty,
                'unit_price'              => $unitPrice,
            ]],
        ];
    }
}
