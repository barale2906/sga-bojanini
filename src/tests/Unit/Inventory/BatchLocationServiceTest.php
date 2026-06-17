<?php

namespace Tests\Unit\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BatchLocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
    }

    public function test_decrements_preferred_location_first_without_going_negative(): void
    {
        $locations = $this->createWarehouseSetup();
        $batch = $this->createBatch($locations['locationA'], 60, $locations['locationB'], 40);

        app(BatchLocationService::class)->decrement($batch->id, 70, $locations['locationA']->id);

        $this->assertSame(0, $this->pivotQuantity($batch->id, $locations['locationA']->id));
        $this->assertSame(30, $this->pivotQuantity($batch->id, $locations['locationB']->id));
    }

    public function test_decrement_does_not_touch_other_locations_when_preferred_covers_quantity(): void
    {
        $locations = $this->createWarehouseSetup();
        $batch = $this->createBatch($locations['locationA'], 60, $locations['locationB'], 40);

        app(BatchLocationService::class)->decrement($batch->id, 10, $locations['locationA']->id);

        $this->assertSame(50, $this->pivotQuantity($batch->id, $locations['locationA']->id));
        $this->assertSame(40, $this->pivotQuantity($batch->id, $locations['locationB']->id));
    }

    public function test_decrement_without_preferred_location_spreads_across_all_locations(): void
    {
        $locations = $this->createWarehouseSetup();
        $batch = $this->createBatch($locations['locationA'], 60, $locations['locationB'], 40);

        app(BatchLocationService::class)->decrement($batch->id, 70, null);

        $this->assertSame(0, $this->pivotQuantity($batch->id, $locations['locationA']->id));
        $this->assertSame(30, $this->pivotQuantity($batch->id, $locations['locationB']->id));
    }

    /**
     * @return array{warehouse: WarehouseModel, locationA: LocationModel, locationB: LocationModel}
     */
    private function createWarehouseSetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => 'Almacén BatchLocation Test',
            'code'      => 'ALM-BL',
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => 'Zona BatchLocation',
            'code'         => 'Z-BL',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $locationA = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => 'Ubicación A',
            'code'      => 'U-BL-A',
            'is_active' => true,
        ]);

        $locationB = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => 'Ubicación B',
            'code'      => 'U-BL-B',
            'is_active' => true,
        ]);

        return compact('warehouse', 'locationA', 'locationB');
    }

    private function createBatch(LocationModel $locationA, int $qtyA, LocationModel $locationB, int $qtyB): BatchModel
    {
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();

        $batch = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-BL-'.uniqid(),
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => $qtyA + $qtyB,
            'quantity_available' => $qtyA + $qtyB,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batch->locations()->attach($locationA->id, ['quantity' => $qtyA]);
        $batch->locations()->attach($locationB->id, ['quantity' => $qtyB]);

        return $batch;
    }

    private function pivotQuantity(int $batchId, int $locationId): int
    {
        return (int) DB::table('batch_location')
            ->where('batch_id', $batchId)
            ->where('location_id', $locationId)
            ->value('quantity');
    }
}
