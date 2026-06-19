<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre el reporte "lotes por vencer": que filtre por almacén (relación
 * indirecta batch -> locations -> zone -> warehouse) y que informe el
 * rango de fechas evaluado (hoy hasta hoy + días solicitados).
 */
class ExpiringReportTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function createWarehouseWithLocation(string $code): LocationModel
    {
        $warehouse = WarehouseModel::create(['name' => "Almacén {$code}", 'code' => $code, 'is_active' => true]);
        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => "Zona {$code}",
            'code'         => "Z-{$code}",
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        return LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => "Ubicación {$code}",
            'code'      => "L-{$code}",
            'is_active' => true,
        ]);
    }

    private function createExpiringBatch(LocationModel $location, int $daysToExpire, string $lot): BatchModel
    {
        $cat  = CategoryModel::first();
        $unit = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $product = ProductModel::create([
            'category_id'  => $cat->id,
            'base_unit_id' => $unit->id,
            'product_type' => 'simple',
            'name'         => "Producto {$lot}",
            'code'         => "PROD-{$lot}",
            'is_active'    => true,
        ]);

        $batch = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => $lot,
            'expiration_date'    => now()->addDays($daysToExpire)->format('Y-m-d'),
            'quantity_received'  => 10,
            'quantity_available' => 10,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batch->locations()->attach($location->id, ['quantity' => 10]);

        return $batch;
    }

    public function test_filters_by_warehouse_through_location_zone_chain(): void
    {
        $locationA = $this->createWarehouseWithLocation('A');
        $locationB = $this->createWarehouseWithLocation('B');

        $this->createExpiringBatch($locationA, 5, 'LOT-A');
        $this->createExpiringBatch($locationB, 5, 'LOT-B');

        $warehouseIdA = $locationA->zone->warehouse_id;

        $response = $this->withHeaders($this->auth())
            ->get("/api/v1/reports/expiring?format=csv&days=10&warehouse_id={$warehouseIdA}");

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('LOT-A', $content);
        $this->assertStringNotContainsString('LOT-B', $content);
    }

    public function test_reports_evaluated_date_range_based_on_days_parameter(): void
    {
        $location = $this->createWarehouseWithLocation('C');
        $this->createExpiringBatch($location, 5, 'LOT-C');

        $response = $this->withHeaders($this->auth())
            ->get('/api/v1/reports/expiring?format=csv&days=10');

        $response->assertStatus(200);

        $expectedFrom = Carbon::today()->toDateString();
        $expectedTo = Carbon::today()->addDays(10)->toDateString();

        $this->assertStringContainsString(
            "Rango de fechas evaluado: {$expectedFrom} a {$expectedTo}",
            $response->getContent(),
        );
    }
}
