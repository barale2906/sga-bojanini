<?php

namespace Tests\Unit\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Inventory\Domain\Services\StockCalculator;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
    }

    public function test_recalculate_summary(): void
    {
        $warehouse = WarehouseModel::create(['name' => 'Alm Calc', 'code' => 'ALM-CALC', 'is_active' => true]);
        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name' => 'Zona',
            'code' => 'Z1',
            'type' => 'ambient',
            'is_active' => true,
        ]);
        $location = LocationModel::create(['zone_id' => $zone->id, 'name' => 'U1', 'code' => 'U1', 'is_active' => true]);

        $generic = GenericProductModel::where('barcode', '000001')->firstOrFail();
        $variant = ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();

        $batch = BatchModel::create([
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-CALC',
            'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
            'quantity_received'  => 75,
            'quantity_available' => 75,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $batch->locations()->attach($location->id, ['quantity' => 75]);

        app(StockCalculator::class)->recalculateSummary($variant->id, $warehouse->id);

        $this->assertDatabaseHas('stock_summaries', [
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $warehouse->id,
            'available_quantity' => 75,
        ]);

        $this->assertSame(75, app(StockCalculator::class)->getCurrentStock($variant->id, $warehouse->id));
    }
}
