<?php

namespace Tests\Feature\Integration;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Integration\Domain\Ports\ClinicalRecordsServiceInterface;
use App\Modules\Integration\Domain\Ports\SchedulingServiceInterface;
use App\Modules\Integration\Infrastructure\Jobs\SyncConsumptionToHCJob;
use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MockIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sga.integrations.use_mock' => true]);

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->firstOrFail();
        Auth::login($admin);
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_get_appointments_returns_mock_data(): void
    {
        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/scheduling/appointments?date_from='.now()->toDateString().'&date_to='.now()->addDays(3)->toDateString());

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('appointment_id', $response->json('data.0'));
    }

    public function test_demand_analysis_identifies_deficit(): void
    {
        $setup   = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();

        $this->createBatch($product->id, 'LOT-LOW', now()->addDays(30)->format('Y-m-d'), 2, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $appointments = [];
        for ($i = 0; $i < 10; $i++) {
            $appointments[] = [
                'appointment_id' => 'CITA-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'patient_id'     => 'PAC-00001',
                'patient_name'   => 'Paciente Test',
                'service_type'   => 'Consulta Dermatológica',
                'scheduled_at'   => now()->addDay()->toIso8601String(),
                'status'         => 'confirmed',
            ];
        }

        $this->mock(SchedulingServiceInterface::class, function ($mock) use ($appointments) {
            $mock->shouldReceive('getUpcomingAppointments')
                ->andReturn($appointments);
            $mock->shouldReceive('getPatientBasicData')->andReturn([]);
        });

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/scheduling/demand-analysis?days_ahead=7');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $deficitProducts = $response->json('data.deficit_products');
        $this->assertNotEmpty($deficitProducts);

        $agujaDeficit = collect($deficitProducts)->firstWhere('product_code', 'AGU-21G');
        $this->assertNotNull($agujaDeficit);
        $this->assertGreaterThan(0, $agujaDeficit['deficit']);
    }

    public function test_register_consumption_deducts_stock(): void
    {
        $setup   = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();

        $this->createBatch($product->id, 'LOT-CONS', now()->addMonths(3)->format('Y-m-d'), 50, $setup['location'], $setup['warehouse']->id);
        $this->recalculateStock($product->id, $setup['warehouse']->id);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/consumptions', [
                'appointment_id'     => 'CITA-00001',
                'patient_identifier' => 'PAC-00001',
                'service_type'       => 'Consulta Dermatológica',
                'warehouse_id'       => $setup['warehouse']->id,
                'items'              => [
                    ['product_id' => $product->id, 'quantity' => 10],
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 1);

        $this->assertDatabaseHas('stock_summaries', [
            'product_id'         => $product->id,
            'warehouse_id'       => $setup['warehouse']->id,
            'available_quantity' => 40,
        ]);

        $this->assertDatabaseHas('consumption_records', [
            'appointment_id' => 'CITA-00001',
            'product_id'     => $product->id,
            'quantity'       => 10,
        ]);
    }

    public function test_sync_job_updates_status(): void
    {
        $setup   = $this->createWarehouseSetup();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();
        $batch   = $this->createBatch($product->id, 'LOT-SYNC', now()->addMonths(3)->format('Y-m-d'), 5, $setup['location'], $setup['warehouse']->id);

        $record = ConsumptionRecordModel::create([
            'appointment_id'     => 'CITA-SYNC',
            'patient_identifier' => 'PAC-SYNC',
            'service_type'       => 'Consulta',
            'product_id'         => $product->id,
            'batch_id'           => $batch->id,
            'quantity'           => 2,
            'user_id'            => UserModel::first()->id,
            'consumed_at'        => now(),
            'sync_status'        => 'pending',
        ]);

        $job = new SyncConsumptionToHCJob('PAC-SYNC', 'CITA-SYNC', [$record->id]);
        $job->handle(app(ClinicalRecordsServiceInterface::class));

        $record->refresh();
        $this->assertSame('synced', $record->sync_status);
        $this->assertNotNull($record->synced_to_hc_at);
    }

    /**
     * @return array{warehouse: WarehouseModel, zone: ZoneModel, location: LocationModel}
     */
    private function createWarehouseSetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => 'Almacén Integration Test',
            'code'      => 'ALM-INT',
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => 'Zona Integration',
            'code'         => 'Z-INT',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $location = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => 'Ubicación Integration',
            'code'      => 'U-INT',
            'is_active' => true,
        ]);

        return compact('warehouse', 'zone', 'location');
    }

    private function createBatch(int $productId, string $lotNumber, string $expirationDate, int $quantity, LocationModel $location, int $warehouseId): BatchModel
    {
        $batch = BatchModel::create([
            'product_id'         => $productId,
            'lot_number'         => $lotNumber,
            'expiration_date'    => $expirationDate,
            'quantity_received'  => $quantity,
            'quantity_available' => $quantity,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batch->locations()->attach($location->id, ['quantity' => $quantity]);

        StockMovementModel::create([
            'warehouse_id'   => $warehouseId,
            'product_id'     => $productId,
            'batch_id'       => $batch->id,
            'location_to_id' => $location->id,
            'movement_type'  => 'entry',
            'quantity'       => $quantity,
            'user_id'        => UserModel::first()->id,
        ]);

        return $batch;
    }

    private function recalculateStock(int $productId, int $warehouseId): void
    {
        app(\App\Modules\Inventory\Domain\Services\StockCalculator::class)
            ->recalculateSummary($productId, $warehouseId);
    }
}
