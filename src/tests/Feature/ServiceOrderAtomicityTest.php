<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Crear una orden de servicio es todo o nada: si una línea falla, no quedan
 * registros de las anteriores ni salidas de inventario de sus insumos.
 */
class ServiceOrderAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private MedicalServiceModel $procedure;
    private GenericProductModel $generic;
    private BatchModel $batch;
    private WarehouseModel $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $this->procedure = MedicalServiceModel::where('type', 'procedure')->first();
        $this->generic   = GenericProductModel::where('barcode', '000001')->firstOrFail();

        $this->createStock(50);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** Un almacén con un lote de 50 unidades del producto genérico. */
    private function createStock(int $quantity): void
    {
        $this->warehouse = WarehouseModel::create(['name' => 'Almacén Órdenes', 'code' => 'ALM-ORD', 'is_active' => true]);
        $zone = ZoneModel::create([
            'warehouse_id' => $this->warehouse->id, 'name' => 'Zona Órdenes',
            'code' => 'Z-ORD', 'type' => 'ambient', 'is_active' => true,
        ]);
        $location = LocationModel::create(['zone_id' => $zone->id, 'name' => 'Ubicación Órdenes', 'code' => 'U-ORD', 'is_active' => true]);

        $variant = ProductVariantModel::where('generic_product_id', $this->generic->id)->firstOrFail();
        $this->batch = BatchModel::create([
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-ORD',
            'expiration_date'    => now()->addMonths(3)->format('Y-m-d'),
            'quantity_received'  => $quantity,
            'quantity_available' => $quantity,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $this->batch->locations()->attach($location->id, ['quantity' => $quantity]);

        $document = MovementDocumentModel::create([
            'document_number' => 'ENT-ORD-' . uniqid(),
            'document_type'   => 'entry',
            'warehouse_id'    => $this->warehouse->id,
            'user_id'         => UserModel::first()->id,
            'status'          => 'confirmed',
        ]);
        StockMovementModel::create([
            'movement_document_id' => $document->id,
            'warehouse_id'         => $this->warehouse->id,
            'product_variant_id'   => $variant->id,
            'batch_id'             => $this->batch->id,
            'location_to_id'       => $location->id,
            'movement_type'        => 'entry',
            'quantity'             => $quantity,
            'user_id'              => UserModel::first()->id,
        ]);
        app(\App\Modules\Inventory\Domain\Services\StockCalculator::class)
            ->recalculateSummary($variant->id, $this->warehouse->id);
    }

    private function payload(array $procedures): array
    {
        return [
            'patient_external_id' => 'EXT-9001',
            'patient_document'    => '1020304050',
            'patient_first_name'  => 'Carlos',
            'patient_last_name'   => 'Pérez',
            'service_date'        => now()->toDateString(),
            'procedures'          => $procedures,
        ];
    }

    private function validLine(array $supplies = []): array
    {
        return array_filter([
            'medical_service_id' => $this->procedure->id,
            'unit_price'         => 250000,
            'quantity'           => 1,
            'supplies'           => $supplies ?: null,
        ], fn ($value) => $value !== null);
    }

    /** Una línea que el servidor rechaza: el id existe pero no es un procedimiento. */
    private function invalidLine(): array
    {
        $notAProcedure = MedicalServiceModel::where('type', '!=', 'procedure')->firstOrFail();

        return [
            'medical_service_id' => $notAProcedure->id,
            'unit_price'         => 1000,
            'quantity'           => 1,
        ];
    }

    private function supply(int $quantity): array
    {
        return [
            'warehouse_id'       => $this->warehouse->id,
            'generic_product_id' => $this->generic->id,
            'quantity'           => $quantity,
        ];
    }

    public function test_si_falla_la_segunda_linea_no_queda_ningun_registro(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $this->payload([$this->validLine(), $this->invalidLine()]))
            ->assertStatus(409);

        $this->assertSame(0, PatientProcedureRecordModel::count());
    }

    public function test_si_falla_la_segunda_linea_no_se_descuentan_los_insumos_de_la_primera(): void
    {
        $documentsBefore = MovementDocumentModel::count();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $this->payload([
                $this->validLine([$this->supply(10)]),
                $this->invalidLine(),
            ]))
            ->assertStatus(409);

        $this->assertSame(0, PatientProcedureRecordModel::count());
        $this->assertSame($documentsBefore, MovementDocumentModel::count(), 'no debe quedar un documento de salida');
        $this->assertSame(0, StockMovementModel::where('movement_type', 'exit')->count());
        $this->assertEquals(50, $this->batch->fresh()->quantity_available);
    }

    public function test_si_los_insumos_de_la_segunda_linea_no_alcanzan_se_deshace_la_primera(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $this->payload([
                $this->validLine([$this->supply(10)]),
                $this->validLine([$this->supply(500)]),
            ]))
            ->assertStatus(409);

        $this->assertSame(0, PatientProcedureRecordModel::count());
        $this->assertSame(0, StockMovementModel::where('movement_type', 'exit')->count());
        $this->assertEquals(50, $this->batch->fresh()->quantity_available);
    }

    public function test_una_orden_valida_con_insumos_sigue_creandose_completa(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $this->payload([
                $this->validLine([$this->supply(10)]),
                $this->validLine(),
            ]))
            ->assertStatus(201);

        $this->assertCount(2, $response->json('data.procedures'));
        $this->assertSame(2, PatientProcedureRecordModel::count());
        $this->assertNotNull(PatientProcedureRecordModel::orderBy('id')->first()->movement_document_id);
        $this->assertSame(1, StockMovementModel::where('movement_type', 'exit')->count());
    }
}
