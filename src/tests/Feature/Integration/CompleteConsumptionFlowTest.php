<?php

namespace Tests\Feature\Integration;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Integration\Infrastructure\Jobs\SyncConsumptionToHCJob;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Flujo end-to-end: consumo → FEFO descuenta stock → job de sync encolado.
 */
class CompleteConsumptionFlowTest extends TestCase
{
    use RefreshDatabase;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sga.integrations.use_mock' => true]);
        Queue::fake();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->auth = $this->bearerAuthFor($admin);
    }

    public function test_complete_consumption_flow(): void
    {
        $setup = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();

        $batchSoon = BatchModel::create([
            'product_id' => $product->id, 'lot_number' => 'LOT-FEFO-SOON',
            'expiration_date' => now()->addDays(15)->format('Y-m-d'),
            'quantity_received' => 20, 'quantity_available' => 20,
            'status' => 'active', 'received_at' => now(),
        ]);
        $batchLater = BatchModel::create([
            'product_id' => $product->id, 'lot_number' => 'LOT-FEFO-LATE',
            'expiration_date' => now()->addDays(90)->format('Y-m-d'),
            'quantity_received' => 50, 'quantity_available' => 50,
            'status' => 'active', 'received_at' => now(),
        ]);
        $batchSoon->locations()->attach($setup['location']->id, ['quantity' => 20]);
        $batchLater->locations()->attach($setup['location']->id, ['quantity' => 50]);

        app(\App\Modules\Inventory\Domain\Services\StockCalculator::class)
            ->recalculateSummary($product->id, $setup['warehouse']->id);

        $costCenter = CostCenterModel::where('type', 'external')->first();
        $service    = MedicalServiceModel::first();

        $this->withHeaders($this->auth)
            ->postJson('/api/v1/consumptions', [
                'appointment_id'     => 'CITA-E2E-001',
                'patient_identifier' => 'PAC-E2E',
                'service_type'       => 'Consulta Dermatológica',
                'warehouse_id'       => $setup['warehouse']->id,
                'cost_center_id'     => $costCenter->id,
                'service_id'         => $service->id,
                'items'              => [
                    ['product_id' => $product->id, 'quantity' => 25],
                ],
            ])
            ->assertStatus(201);

        $batchSoon->refresh();
        $batchLater->refresh();
        $this->assertSame(0, $batchSoon->quantity_available);
        $this->assertSame(45, $batchLater->quantity_available);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 45,
        ]);

        Queue::assertPushed(SyncConsumptionToHCJob::class);
    }

    /**
     * @return array{warehouse: WarehouseModel, zone: ZoneModel, location: LocationModel}
     */
    private function createWarehouseSetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name' => 'Almacén Consumo E2E', 'code' => 'ALM-CONS', 'is_active' => true,
        ]);
        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id, 'name' => 'Zona', 'code' => 'Z-CONS',
            'type' => 'ambient', 'is_active' => true,
        ]);
        $location = LocationModel::create([
            'zone_id' => $zone->id, 'name' => 'Ubicación', 'code' => 'U-CONS', 'is_active' => true,
        ]);

        StockMovementModel::create([
            'warehouse_id' => $warehouse->id,
            'product_id'   => ProductModel::where('code', 'AGU-21G')->value('id'),
            'movement_type' => 'entry',
            'quantity'     => 70,
            'user_id'      => UserModel::first()->id,
        ]);

        return compact('warehouse', 'zone', 'location');
    }
}
