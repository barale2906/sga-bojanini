<?php

namespace Tests\Unit\Purchasing;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
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
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();
        $product->update(['reorder_point' => 50, 'min_stock' => 20]);
        $supplier = SupplierModel::firstOrFail();
        $product->suppliers()->syncWithoutDetaching([
            $supplier->id => ['lead_time_days' => 10, 'is_preferred' => true, 'unit_price' => 1000],
        ]);

        $warehouse = WarehouseModel::create(['name' => 'Alm Reorder', 'code' => 'ALM-RO', 'is_active' => true]);
        $userId = UserModel::where('email', 'alexanderbarajas@gmail.com')->value('id');

        StockSummaryModel::create([
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'total_quantity'     => 5,
            'available_quantity' => 5,
        ]);

        for ($i = 0; $i < 30; $i++) {
            StockMovementModel::create([
                'warehouse_id'  => $warehouse->id,
                'product_id'    => $product->id,
                'movement_type' => 'exit',
                'quantity'      => -10,
                'user_id'       => $userId,
            ]);
        }

        $suggestions = app(ReorderPointCalculator::class)->generateSuggestions();
        $aguja = collect($suggestions)->firstWhere('product_code', 'AGU-21G');

        $this->assertNotNull($aguja);
        $this->assertGreaterThan(0, $aguja['suggested_quantity']);
        $this->assertGreaterThan(0, $aguja['daily_consumption']);
    }
}
