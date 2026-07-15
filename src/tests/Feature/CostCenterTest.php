<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostCenterTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── Seeder ───────────────────────────────────────────────────────────────

    public function test_seed_genera_centros_de_costo_internos_y_externos(): void
    {
        $this->assertDatabaseHas('cost_centers', ['code' => 'ENF',      'type' => 'internal']);
        $this->assertDatabaseHas('cost_centers', ['code' => 'ANTI-ENV', 'type' => 'internal']);
        $this->assertDatabaseHas('cost_centers', ['code' => 'PAC',      'type' => 'external']);
    }

    public function test_seed_genera_servicios_medicos_iniciales(): void
    {
        $this->assertDatabaseHas('medical_services', ['code' => 'CONS-GEN']);
        $this->assertDatabaseHas('medical_services', ['code' => 'CIR-APEN']);
        $this->assertDatabaseHas('medical_services', ['code' => 'UCI']);
    }

    // ─── CRUD Centros de Costo ────────────────────────────────────────────────

    public function test_listar_centros_de_costo(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/cost-centers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(9, 'data'); // 8 internal + 1 external del seeder
    }

    public function test_filtrar_centros_por_tipo_interno(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/cost-centers?type=internal')
            ->assertOk()
            ->assertJsonCount(8, 'data');

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/cost-centers?type=external')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filtrar_centros_activos(): void
    {
        CostCenterModel::where('code', 'ENF')->update(['is_active' => false]);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/cost-centers?is_active=1')
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }

    public function test_crud_centro_de_costo_interno(): void
    {
        // Crear
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/cost-centers', [
                'code'        => 'TEST-INT',
                'name'        => 'Centro Test Interno',
                'type'        => 'internal',
                'description' => 'Área de pruebas',
                'is_active'   => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'TEST-INT')
            ->assertJsonPath('data.type', 'internal')
            ->assertJsonPath('data.is_external', false)
            ->assertJsonPath('data.type_label', 'Interno');

        $id = $response->json('data.id');

        // Leer
        $this->withHeaders($this->auth())
            ->getJson("/api/v1/cost-centers/{$id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'TEST-INT');

        // Actualizar
        $this->withHeaders($this->auth())
            ->putJson("/api/v1/cost-centers/{$id}", [
                'code'      => 'TEST-INT',
                'name'      => 'Centro Actualizado',
                'type'      => 'internal',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Centro Actualizado')
            ->assertJsonPath('data.is_active', false);

        // Eliminar
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/cost-centers/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('cost_centers', ['id' => $id]);
    }

    public function test_crud_centro_de_costo_externo(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/cost-centers', [
                'code' => 'PAC-TEST',
                'name' => 'Pacientes Test',
                'type' => 'external',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_external', true)
            ->assertJsonPath('data.type_label', 'Externo (Pacientes)');
    }

    public function test_codigo_duplicado_en_centro_rechazado_por_validacion(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/cost-centers', [
                'code' => 'ENF', // ya existe en el seeder
                'name' => 'Duplicado',
                'type' => 'internal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_tipo_invalido_rechazado(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/cost-centers', [
                'code' => 'BAD-TYPE',
                'name' => 'Tipo inválido',
                'type' => 'invalid_type',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_centro_no_encontrado_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/cost-centers/99999')
            ->assertNotFound();
    }

    public function test_no_eliminar_centro_con_movimientos_asociados(): void
    {
        $center  = CostCenterModel::where('code', 'ENF')->first();
        $variant = ProductVariantModel::first();
        $user    = UserModel::first();
        $wh      = WarehouseModel::create(['name' => 'WH-TEST', 'code' => 'WH-T', 'is_active' => true]);

        $document = \App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel::create([
            'document_number' => 'SAL-TEST-001',
            'document_type'   => 'exit',
            'warehouse_id'    => $wh->id,
            'cost_center_id'  => $center->id,
            'user_id'         => $user->id,
            'status'          => 'confirmed',
        ]);

        StockMovementModel::create([
            'movement_document_id' => $document->id,
            'warehouse_id'         => $wh->id,
            'product_variant_id'   => $variant->id,
            'movement_type'        => 'exit',
            'quantity'             => -1,
            'cost_center_id'       => $center->id,
            'user_id'              => $user->id,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/cost-centers/{$center->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ─── Reglas de negocio en salidas de inventario ───────────────────────────

    public function test_salida_sin_cost_center_falla_validacion(): void
    {
        $setup   = $this->createInventorySetup();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [['generic_product_id' => $setup['generic']->id, 'quantity' => 5]],
                // sin cost_center_id
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cost_center_id']);
    }

    public function test_salida_con_centro_interno_no_requiere_datos_paciente(): void
    {
        $setup  = $this->createInventorySetup();
        $center = CostCenterModel::where('type', 'internal')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $setup['generic']->id, 'quantity' => 5]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cost_center_id', $center->id);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $setup['variant']->id,
            'movement_type'      => 'exit',
            'cost_center_id'     => $center->id,
        ]);
    }

    public function test_salida_con_centro_externo_registra_datos_paciente(): void
    {
        $setup   = $this->createInventorySetup();
        $center  = CostCenterModel::where('type', 'external')->first();
        $service = MedicalServiceModel::first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'        => $setup['warehouse']->id,
                'cost_center_id'      => $center->id,
                'service_id'          => $service->id,
                'patient_document'    => '1012345678',
                'patient_external_id' => 'EXT-00892',
                'items'               => [['generic_product_id' => $setup['generic']->id, 'quantity' => 3]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.cost_center_id', $center->id)
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.patient_document', '1012345678')
            ->assertJsonPath('data.patient_external_id', 'EXT-00892');

        $this->assertDatabaseHas('stock_movements', [
            'cost_center_id'      => $center->id,
            'service_id'          => $service->id,
            'patient_document'    => '1012345678',
            'patient_external_id' => 'EXT-00892',
        ]);
    }

    public function test_salida_con_centro_externo_sin_service_id_retorna_409(): void
    {
        $setup  = $this->createInventorySetup();
        $center = CostCenterModel::where('type', 'external')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'        => $setup['warehouse']->id,
                'cost_center_id'      => $center->id,
                // sin service_id
                'patient_document'    => '1012345678',
                'patient_external_id' => 'EXT-00892',
                'items'               => [['generic_product_id' => $setup['generic']->id, 'quantity' => 2]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_salida_con_centro_externo_sin_documento_paciente_retorna_409(): void
    {
        $setup   = $this->createInventorySetup();
        $center  = CostCenterModel::where('type', 'external')->first();
        $service = MedicalServiceModel::first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'        => $setup['warehouse']->id,
                'cost_center_id'      => $center->id,
                'service_id'          => $service->id,
                // sin patient_document
                'patient_external_id' => 'EXT-00892',
                'items'               => [['generic_product_id' => $setup['generic']->id, 'quantity' => 2]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_salida_con_centro_externo_sin_id_externo_paciente_retorna_409(): void
    {
        $setup   = $this->createInventorySetup();
        $center  = CostCenterModel::where('type', 'external')->first();
        $service = MedicalServiceModel::first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'     => $setup['warehouse']->id,
                'cost_center_id'   => $center->id,
                'service_id'       => $service->id,
                'patient_document' => '1012345678',
                // sin patient_external_id
                'items'            => [['generic_product_id' => $setup['generic']->id, 'quantity' => 2]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_salida_con_centro_inactivo_retorna_409(): void
    {
        $setup  = $this->createInventorySetup();
        $center = CostCenterModel::where('type', 'internal')->first();
        $center->update(['is_active' => false]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $center->id,
                'items'          => [['generic_product_id' => $setup['generic']->id, 'quantity' => 2]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_respuesta_salida_incluye_centro_y_servicio_cargados(): void
    {
        $setup   = $this->createInventorySetup();
        $center  = CostCenterModel::where('type', 'external')->first();
        $service = MedicalServiceModel::first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'        => $setup['warehouse']->id,
                'cost_center_id'      => $center->id,
                'service_id'          => $service->id,
                'patient_document'    => '9876543210',
                'patient_external_id' => 'EXT-TEST',
                'items'               => [['generic_product_id' => $setup['generic']->id, 'quantity' => 1]],
            ])
            ->assertStatus(201);

        $response->assertJsonPath('data.cost_center.id', $center->id)
            ->assertJsonPath('data.cost_center.type', 'external')
            ->assertJsonPath('data.medical_service.id', $service->id);
    }

    // ─── Filtros en listado de movimientos ────────────────────────────────────

    public function test_filtrar_movimientos_por_centro_de_costo(): void
    {
        $setup    = $this->createInventorySetup();
        $internal = CostCenterModel::where('type', 'internal')->first();
        $external = CostCenterModel::where('type', 'external')->first();
        $service  = MedicalServiceModel::first();

        // Una salida interna
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $internal->id,
                'items'          => [['generic_product_id' => $setup['generic']->id, 'quantity' => 1]],
            ]);

        // Una salida externa
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'        => $setup['warehouse']->id,
                'cost_center_id'      => $external->id,
                'service_id'          => $service->id,
                'patient_document'    => '1111111111',
                'patient_external_id' => 'EXT-001',
                'items'               => [['generic_product_id' => $setup['generic']->id, 'quantity' => 1]],
            ]);

        // Filtrar solo por centro interno
        $this->withHeaders($this->auth())
            ->getJson("/api/v1/movements?cost_center_id={$internal->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Filtrar por tipo externo
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/movements?cost_center_type=external')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @return array{warehouse: WarehouseModel, location: LocationModel, generic: GenericProductModel, variant: ProductVariantModel}
     */
    private function createInventorySetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name' => 'Almacén CC Test', 'code' => 'ALM-CC', 'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => 'Zona CC',
            'code'         => 'Z-CC',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $location = LocationModel::create([
            'zone_id' => $zone->id, 'name' => 'Ubicación CC', 'code' => 'U-CC', 'is_active' => true,
        ]);

        $generic = GenericProductModel::where('barcode', '000001')->firstOrFail();
        $variant = ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();
        $user    = UserModel::first();

        $batch = BatchModel::create([
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-CC-TEST',
            'expiration_date'    => now()->addYear()->format('Y-m-d'),
            'quantity_received'  => 200,
            'quantity_available' => 200,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        $batch->locations()->attach($location->id, ['quantity' => 200]);

        $entryDoc = \App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel::create([
            'document_number' => 'ENT-CC-TEST-001',
            'document_type'   => 'entry',
            'warehouse_id'    => $warehouse->id,
            'user_id'         => $user->id,
            'status'          => 'confirmed',
        ]);

        StockMovementModel::create([
            'movement_document_id' => $entryDoc->id,
            'warehouse_id'         => $warehouse->id,
            'product_variant_id'   => $variant->id,
            'batch_id'             => $batch->id,
            'location_to_id'       => $location->id,
            'movement_type'        => 'entry',
            'quantity'             => 200,
            'user_id'              => $user->id,
        ]);

        app(\App\Modules\Inventory\Domain\Services\StockCalculator::class)
            ->recalculateSummary($variant->id, $warehouse->id);

        return compact('warehouse', 'location', 'generic', 'variant');
    }
}
