<?php

namespace Tests\Unit\Purchasing;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Purchasing\Domain\Services\ReorderPointCalculator;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderPointCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);
    }

    public function test_calculates_suggested_quantity(): void
    {
        $generic = GenericProductModel::where('barcode', '000001')->firstOrFail();
        $generic->update(['reorder_point' => 50, 'min_stock' => 20]);

        $variant  = ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();
        $supplier = SupplierModel::firstOrFail();

        $variant->suppliers()->syncWithoutDetaching([
            $supplier->id => ['lead_time_days' => 10, 'is_preferred' => true, 'unit_price' => 1000],
        ]);

        $warehouse = WarehouseModel::create(['name' => 'Alm Reorder', 'code' => 'ALM-RO', 'is_active' => true]);
        $userId = UserModel::firstOrFail()->id;

        StockSummaryModel::create([
            'product_variant_id' => $variant->id,
            'warehouse_id'       => $warehouse->id,
            'total_quantity'     => 5,
            'available_quantity' => 5,
        ]);

        $doc = MovementDocumentModel::create([
            'document_number' => 'SAL-CALC-TEST-001',
            'document_type'   => 'exit',
            'warehouse_id'    => $warehouse->id,
            'user_id'         => $userId,
            'status'          => 'confirmed',
        ]);

        for ($i = 0; $i < 30; $i++) {
            StockMovementModel::create([
                'movement_document_id' => $doc->id,
                'warehouse_id'         => $warehouse->id,
                'product_variant_id'   => $variant->id,
                'movement_type'        => 'exit',
                'quantity'             => -10,
                'user_id'              => $userId,
            ]);
        }

        $suggestions = app(ReorderPointCalculator::class)->generateSuggestions();
        $aguja = collect($suggestions)->firstWhere('generic_product_id', $generic->id);

        $this->assertNotNull($aguja);
        $this->assertGreaterThan(0, $aguja['suggested_quantity']);
        $this->assertGreaterThan(0, $aguja['daily_consumption']);
    }
}
