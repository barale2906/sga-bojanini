<?php

namespace Tests\Unit\Inventory;

use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Domain\Exceptions\LocationOutsideWarehouseException;
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

    public function test_withdraw_takes_preferred_location_first_without_going_negative(): void
    {
        $setup = $this->createWarehouseSetup('A');
        $batch = $this->createBatch([$setup['locationA']->id => 60, $setup['locationB']->id => 40]);

        app(BatchLocationService::class)->withdraw($batch->id, $setup['warehouse']->id, 70, $setup['locationA']->id);

        $this->assertSame(0.0, $this->pivotQuantity($batch->id, $setup['locationA']->id));
        $this->assertSame(30.0, $this->pivotQuantity($batch->id, $setup['locationB']->id));
        $this->assertSame(30.0, $batch->refresh()->quantity_available);
    }

    public function test_withdraw_does_not_touch_other_locations_when_preferred_covers_quantity(): void
    {
        $setup = $this->createWarehouseSetup('A');
        $batch = $this->createBatch([$setup['locationA']->id => 60, $setup['locationB']->id => 40]);

        app(BatchLocationService::class)->withdraw($batch->id, $setup['warehouse']->id, 10, $setup['locationA']->id);

        $this->assertSame(50.0, $this->pivotQuantity($batch->id, $setup['locationA']->id));
        $this->assertSame(40.0, $this->pivotQuantity($batch->id, $setup['locationB']->id));
    }

    public function test_withdraw_without_preferred_location_spreads_across_locations_of_the_warehouse(): void
    {
        $setup = $this->createWarehouseSetup('A');
        $batch = $this->createBatch([$setup['locationA']->id => 60, $setup['locationB']->id => 40]);

        app(BatchLocationService::class)->withdraw($batch->id, $setup['warehouse']->id, 70);

        $this->assertSame(0.0, $this->pivotQuantity($batch->id, $setup['locationA']->id));
        $this->assertSame(30.0, $this->pivotQuantity($batch->id, $setup['locationB']->id));
    }

    public function test_withdraw_never_takes_stock_from_another_warehouse(): void
    {
        $origin = $this->createWarehouseSetup('A');
        $other  = $this->createWarehouseSetup('B');
        $batch  = $this->createBatch([$origin['locationA']->id => 2, $other['locationA']->id => 5]);

        try {
            app(BatchLocationService::class)->withdraw($batch->id, $origin['warehouse']->id, 3);
            $this->fail('Se esperaba InsufficientStockException.');
        } catch (InsufficientStockException) {
            // El almacén de origen solo tiene 2 unidades: no debe completar con el otro almacén.
        }

        $this->assertSame(2.0, $this->pivotQuantity($batch->id, $origin['locationA']->id));
        $this->assertSame(5.0, $this->pivotQuantity($batch->id, $other['locationA']->id));
    }

    public function test_withdraw_rejects_preferred_location_of_another_warehouse(): void
    {
        $origin = $this->createWarehouseSetup('A');
        $other  = $this->createWarehouseSetup('B');
        $batch  = $this->createBatch([$origin['locationA']->id => 5, $other['locationA']->id => 5]);

        $this->expectException(LocationOutsideWarehouseException::class);

        app(BatchLocationService::class)->withdraw($batch->id, $origin['warehouse']->id, 1, $other['locationA']->id);
    }

    public function test_resolve_source_location_picks_where_the_batch_is_stored_in_the_warehouse(): void
    {
        $origin  = $this->createWarehouseSetup('A');
        $other   = $this->createWarehouseSetup('B');
        $batch   = $this->createBatch([$origin['locationA']->id => 3, $origin['locationB']->id => 8, $other['locationA']->id => 20]);
        $service = app(BatchLocationService::class);

        // Sugerida válida y con stock: se respeta.
        $this->assertSame($origin['locationA']->id, $service->resolveSourceLocation($batch->id, $origin['warehouse']->id, $origin['locationA']->id));
        // Sugerida de otro almacén o sin sugerencia: la ubicación del almacén con más stock del lote.
        $this->assertSame($origin['locationB']->id, $service->resolveSourceLocation($batch->id, $origin['warehouse']->id, $other['locationA']->id));
        $this->assertSame($origin['locationB']->id, $service->resolveSourceLocation($batch->id, $origin['warehouse']->id));
    }

    public function test_deposit_rejects_location_of_another_warehouse(): void
    {
        $origin = $this->createWarehouseSetup('A');
        $other  = $this->createWarehouseSetup('B');
        $batch  = $this->createBatch([$origin['locationA']->id => 5]);

        $this->expectException(LocationOutsideWarehouseException::class);

        app(BatchLocationService::class)->deposit($batch->id, $origin['warehouse']->id, $other['locationA']->id, 1);
    }

    public function test_sync_batch_marks_expired_batch_with_stock_as_expired(): void
    {
        $setup = $this->createWarehouseSetup('A');
        $batch = $this->createBatch([$setup['locationA']->id => 5], now()->subDay()->format('Y-m-d'));

        app(BatchLocationService::class)->syncBatch($batch->id);

        $this->assertSame('expired', $batch->refresh()->status);
        $this->assertSame(5.0, $batch->quantity_available);
    }

    public function test_sync_batch_marks_empty_batch_as_depleted(): void
    {
        $setup = $this->createWarehouseSetup('A');
        $batch = $this->createBatch([$setup['locationA']->id => 5]);

        app(BatchLocationService::class)->withdraw($batch->id, $setup['warehouse']->id, 5);

        $this->assertSame('depleted', $batch->refresh()->status);
        $this->assertSame(0.0, $batch->quantity_available);
    }

    /**
     * @return array{warehouse: WarehouseModel, locationA: LocationModel, locationB: LocationModel}
     */
    private function createWarehouseSetup(string $suffix): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => "Almacén BatchLocation {$suffix}",
            'code'      => "ALM-BL-{$suffix}",
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => "Zona BatchLocation {$suffix}",
            'code'         => "Z-BL-{$suffix}",
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $locationA = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => "Ubicación A {$suffix}",
            'code'      => "U-BL-A-{$suffix}",
            'is_active' => true,
        ]);

        $locationB = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => "Ubicación B {$suffix}",
            'code'      => "U-BL-B-{$suffix}",
            'is_active' => true,
        ]);

        return compact('warehouse', 'locationA', 'locationB');
    }

    /** @param array<int, int> $quantitiesByLocation */
    private function createBatch(array $quantitiesByLocation, ?string $expirationDate = null): BatchModel
    {
        $generic = GenericProductModel::where('barcode', '000001')->firstOrFail();
        $variant = ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();
        $total   = array_sum($quantitiesByLocation);

        $batch = BatchModel::create([
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-BL-'.uniqid(),
            'expiration_date'    => $expirationDate ?? now()->addDays(30)->format('Y-m-d'),
            'quantity_received'  => $total,
            'quantity_available' => $total,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        foreach ($quantitiesByLocation as $locationId => $quantity) {
            $batch->locations()->attach($locationId, ['quantity' => $quantity]);
        }

        return $batch;
    }

    private function pivotQuantity(int $batchId, int $locationId): float
    {
        return (float) DB::table('batch_location')
            ->where('batch_id', $batchId)
            ->where('location_id', $locationId)
            ->value('quantity');
    }
}
