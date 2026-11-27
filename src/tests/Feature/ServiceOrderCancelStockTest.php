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
 * Anular una orden de servicio sin facturar devuelve sus insumos al inventario
 * (mismo lote y ubicación); una orden facturada no se puede anular.
 */
class ServiceOrderCancelStockTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private MedicalServiceModel $procedure;
    private GenericProductModel $generic;
    private BatchModel $batch;
    private LocationModel $location;
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
        $location = $this->location = LocationModel::create(['zone_id' => $zone->id, 'name' => 'Ubicación Órdenes', 'code' => 'U-ORD', 'is_active' => true]);

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

    private function createOrder(array $lines): string
    {
        return $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $this->payload($lines))
            ->assertStatus(201)
            ->json('data.order_number');
    }

    private function cancel(string $orderNumber): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->auth())->postJson("/api/v1/service-orders/{$orderNumber}/cancel");
    }

    private function locationQuantity(): float
    {
        return (float) \DB::table('batch_location')
            ->where('batch_id', $this->batch->id)
            ->where('location_id', $this->location->id)
            ->value('quantity');
    }

    public function test_anular_devuelve_los_insumos_al_mismo_lote_y_ubicacion(): void
    {
        $orderNumber = $this->createOrder([
            $this->validLine([$this->supply(10)]),
            $this->validLine([$this->supply(5)]),
        ]);
        $this->assertEquals(35, $this->locationQuantity());

        $this->cancel($orderNumber)
            ->assertOk()
            ->assertJsonPath('data.billing_status', 'cancelled');

        $this->assertEquals(50, $this->locationQuantity());
        $this->assertEquals(50, $this->batch->fresh()->quantity_available);
        $this->assertEquals(50, $this->batch->fresh()->quantity_received, 'recibido no debe inflarse');
        $this->assertDatabaseHas('stock_summaries', [
            'warehouse_id'       => $this->warehouse->id,
            'available_quantity' => 50,
        ]);
    }

    public function test_la_devolucion_queda_registrada_como_ajuste_confirmado_con_referencia(): void
    {
        $orderNumber = $this->createOrder([$this->validLine([$this->supply(10)])]);
        $exitDocumentId = PatientProcedureRecordModel::first()->movement_document_id;

        $this->cancel($orderNumber)->assertOk();

        $adjustment = MovementDocumentModel::where('document_type', 'adjustment')->firstOrFail();
        $this->assertSame('confirmed', $adjustment->status->value ?? $adjustment->status);
        $this->assertSame("Anulación de la orden {$orderNumber}", $adjustment->reason);

        $line = StockMovementModel::where('movement_document_id', $adjustment->id)->firstOrFail();
        $this->assertEquals(10, $line->quantity);
        $this->assertSame($this->batch->id, (int) $line->batch_id);
        $this->assertSame($this->location->id, (int) $line->location_to_id);
        $this->assertSame('movement_document_reversal', $line->reference_type);
        $this->assertSame($exitDocumentId, (int) $line->reference_id);
    }

    public function test_anular_una_orden_sin_insumos_no_crea_movimientos(): void
    {
        $orderNumber = $this->createOrder([$this->validLine()]);
        $documentsBefore = MovementDocumentModel::count();

        $this->cancel($orderNumber)->assertOk();

        $this->assertSame($documentsBefore, MovementDocumentModel::count());
        $this->assertEquals(50, $this->locationQuantity());
    }

    public function test_una_orden_facturada_no_se_anula_ni_devuelve_insumos(): void
    {
        $orderNumber = $this->createOrder([$this->validLine([$this->supply(10)])]);

        $this->withHeaders($this->auth())->postJson("/api/v1/service-orders/{$orderNumber}/bill")->assertOk();
        $this->cancel($orderNumber)->assertStatus(409);

        $this->assertEquals(40, $this->locationQuantity());
        $this->assertSame(0, MovementDocumentModel::where('document_type', 'adjustment')->count());
    }

    public function test_anular_dos_veces_no_devuelve_los_insumos_dos_veces(): void
    {
        $orderNumber = $this->createOrder([$this->validLine([$this->supply(10)])]);

        $this->cancel($orderNumber)->assertOk();
        $this->cancel($orderNumber)->assertStatus(409);

        $this->assertEquals(50, $this->locationQuantity());
        $this->assertSame(1, MovementDocumentModel::where('document_type', 'adjustment')->count());
    }

    public function test_anular_requiere_el_permiso_editar_y_facturar_sigue_con_ver(): void
    {
        $orderNumber = $this->createOrder([$this->validLine([$this->supply(10)])]);

        $user = UserModel::create([
            'name' => 'Solo ver órdenes', 'email' => 'solo_ver_ordenes@sga.test',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $user->givePermissionTo('ordenes_servicio.ver');
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $headers = ['Authorization' => 'Bearer ' . $user->createToken('test', [])->plainTextToken];

        $this->withHeaders($headers)->postJson("/api/v1/service-orders/{$orderNumber}/cancel")->assertForbidden();
        $this->assertEquals(40, $this->locationQuantity());

        $user->givePermissionTo('ordenes_servicio.editar');
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->withHeaders($headers)->postJson("/api/v1/service-orders/{$orderNumber}/cancel")->assertOk();
        $this->assertEquals(50, $this->locationQuantity());
    }
}
