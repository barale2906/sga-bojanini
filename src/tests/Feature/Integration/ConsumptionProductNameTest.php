<?php

namespace Tests\Feature\Integration;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Integration\Infrastructure\Jobs\SyncConsumptionToHCJob;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * El recurso de consumos debe traer el producto consumido (id, código de
 * barras y nombre) al registrar, listar y consultar.
 */
class ConsumptionProductNameTest extends TestCase
{
    use RefreshDatabase;

    private array $auth;
    private GenericProductModel $generic;
    private WarehouseModel $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([SyncConsumptionToHCJob::class]);

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $this->auth    = $this->bearerAuthFor(UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail());
        $this->generic = GenericProductModel::where('barcode', '000001')->firstOrFail();

        $this->createStock();
    }

    private function createStock(): void
    {
        $this->warehouse = WarehouseModel::create(['name' => 'Almacén Consumos', 'code' => 'ALM-CON', 'is_active' => true]);
        $zone = ZoneModel::create([
            'warehouse_id' => $this->warehouse->id, 'name' => 'Zona Consumos',
            'code' => 'Z-CON', 'type' => 'ambient', 'is_active' => true,
        ]);
        $location = LocationModel::create(['zone_id' => $zone->id, 'name' => 'Ubicación Consumos', 'code' => 'U-CON', 'is_active' => true]);
        $variant  = ProductVariantModel::where('generic_product_id', $this->generic->id)->firstOrFail();

        $batch = BatchModel::create([
            'product_variant_id' => $variant->id, 'lot_number' => 'LOT-CON',
            'expiration_date'    => now()->addMonths(3)->format('Y-m-d'),
            'quantity_received'  => 50, 'quantity_available' => 50,
            'status'             => 'active', 'received_at' => now(),
        ]);
        $batch->locations()->attach($location->id, ['quantity' => 50]);

        $document = MovementDocumentModel::create([
            'document_number' => 'ENT-CON-' . uniqid(), 'document_type' => 'entry',
            'warehouse_id' => $this->warehouse->id, 'user_id' => UserModel::first()->id, 'status' => 'confirmed',
        ]);
        StockMovementModel::create([
            'movement_document_id' => $document->id, 'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $variant->id, 'batch_id' => $batch->id, 'location_to_id' => $location->id,
            'movement_type' => 'entry', 'quantity' => 50, 'user_id' => UserModel::first()->id,
        ]);
        app(\App\Modules\Inventory\Domain\Services\StockCalculator::class)
            ->recalculateSummary($variant->id, $this->warehouse->id);
    }

    private function register(): array
    {
        return $this->withHeaders($this->auth)
            ->postJson('/api/v1/consumptions', [
                'warehouse_id'   => $this->warehouse->id,
                'cost_center_id' => CostCenterModel::where('type', 'internal')->firstOrFail()->id,
                'items'          => [['generic_product_id' => $this->generic->id, 'quantity' => 3]],
            ])
            ->assertCreated()
            ->json('data');
    }

    public function test_registrar_devuelve_el_producto_consumido(): void
    {
        $record = $this->register()['records'][0];

        $this->assertSame($this->generic->id, $record['product_id']);
        $this->assertSame($this->generic->name, $record['product_name']);
        $this->assertSame($this->generic->barcode, $record['product_code']);
        $this->assertSame('LOT-CON', $record['lot_number']);
    }

    public function test_listar_devuelve_el_nombre_del_producto(): void
    {
        $this->register();

        $item = $this->withHeaders($this->auth)->getJson('/api/v1/consumptions')
            ->assertOk()
            ->json('data.items.0');

        $this->assertSame($this->generic->name, $item['product_name']);
        $this->assertSame($this->generic->barcode, $item['product_code']);
    }

    public function test_consultar_devuelve_el_nombre_del_producto(): void
    {
        $id = $this->register()['records'][0]['id'];

        $this->withHeaders($this->auth)->getJson("/api/v1/consumptions/{$id}")
            ->assertOk()
            ->assertJsonPath('data.product_name', $this->generic->name)
            ->assertJsonPath('data.product_id', $this->generic->id);
    }

    public function test_el_resto_de_los_campos_del_recurso_no_cambia(): void
    {
        $record = $this->register()['records'][0];

        $this->assertSame(
            ['id', 'appointment_id', 'patient_identifier', 'service_type', 'product_id', 'product_code',
                'product_name', 'batch_id', 'lot_number', 'quantity', 'user_id', 'consumed_at',
                'sync_status', 'synced_to_hc_at'],
            array_keys($record),
        );
        $this->assertSame('pending', $record['sync_status']);
    }
}
