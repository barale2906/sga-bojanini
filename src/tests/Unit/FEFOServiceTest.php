<?php

namespace Tests\Unit;

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

    public function test_fefo_selecciona_lote_con_vencimiento_mas_proximo_primero(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batchSoon = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-SOON',
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => 50,
            'quantity_available' => 50,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batchLater = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-LATER',
            'expiration_date'    => now()->addDays(90)->format('Y-m-d'),
            'quantity_received'  => 100,
            'quantity_available' => 100,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $this->attachBatchToLocation($batchSoon, $setup['location']);
        $this->attachBatchToLocation($batchLater, $setup['location']);

        $service = app(FEFOService::class);
        $selected = $service->selectBatchesForExit($product->id, $setup['warehouse']->id, 30);

        $this->assertCount(1, $selected);
        $this->assertSame($batchSoon->id, $selected[0]['batch_id']);
        $this->assertSame(30, $selected[0]['quantity']);
    }

    public function test_fefo_lanza_excepcion_si_no_hay_stock_suficiente(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $batch = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-LOW',
            'expiration_date'    => now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => 10,
            'quantity_available' => 10,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $this->attachBatchToLocation($batch, $setup['location']);

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

    private function attachBatchToLocation(BatchModel $batch, LocationModel $location): void
    {
        $batch->locations()->attach($location->id, ['quantity' => $batch->quantity_available]);
    }
}
