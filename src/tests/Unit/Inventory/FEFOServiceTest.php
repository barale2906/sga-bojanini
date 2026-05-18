<?php

namespace Tests\Unit\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FEFOServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
    }

    public function test_selects_batch_with_earliest_expiry(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();

        $batchSoon = $this->createBatch($product->id, 'LOT-SOON', now()->addDays(30), 50, $setup['location']);
        $batchLater = $this->createBatch($product->id, 'LOT-LATER', now()->addDays(90), 100, $setup['location']);

        $selected = app(FEFOService::class)->selectBatchesForExit($product->id, $setup['warehouse']->id, 30);

        $this->assertCount(1, $selected);
        $this->assertSame($batchSoon->id, $selected[0]['batch_id']);
    }

    public function test_distributes_across_multiple_batches(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();

        $batchSoon = $this->createBatch($product->id, 'LOT-SOON', now()->addDays(30), 30, $setup['location']);
        $batchLater = $this->createBatch($product->id, 'LOT-LATER', now()->addDays(90), 50, $setup['location']);

        $selected = app(FEFOService::class)->selectBatchesForExit($product->id, $setup['warehouse']->id, 50);

        $this->assertCount(2, $selected);
        $this->assertSame($batchSoon->id, $selected[0]['batch_id']);
        $this->assertSame(30, $selected[0]['quantity']);
        $this->assertSame($batchLater->id, $selected[1]['batch_id']);
        $this->assertSame(20, $selected[1]['quantity']);
    }

    public function test_throws_when_insufficient_stock(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();

        $this->createBatch($product->id, 'LOT-LOW', now()->addDays(30), 10, $setup['location']);

        $this->expectException(InsufficientStockException::class);

        app(FEFOService::class)->selectBatchesForExit($product->id, $setup['warehouse']->id, 100);
    }

    /**
     * @return array{warehouse: WarehouseModel, zone: ZoneModel, location: LocationModel}
     */
    private function createWarehouseSetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => 'Almacén FEFO Test',
            'code'      => 'ALM-FEFO',
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => 'Zona FEFO',
            'code'         => 'Z-FEFO',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $location = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => 'Ubicación FEFO',
            'code'      => 'U-FEFO',
            'is_active' => true,
        ]);

        return compact('warehouse', 'zone', 'location');
    }

    private function createBatch(int $productId, string $lot, $expiration, int $qty, LocationModel $location): BatchModel
    {
        $batch = BatchModel::create([
            'product_id'         => $productId,
            'lot_number'         => $lot,
            'expiration_date'    => $expiration->format('Y-m-d'),
            'quantity_received'  => $qty,
            'quantity_available' => $qty,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batch->locations()->attach($location->id, ['quantity' => $qty]);

        return $batch;
    }
}
