<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests de integración para los endpoints de Órdenes de Servicio.
 */
class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private MedicalServiceModel $procedure;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $this->procedure = MedicalServiceModel::where('type', 'procedure')->first();
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'patient_external_id' => 'EXT-9001',
            'patient_document'    => '1020304050',
            'patient_first_name'  => 'Carlos',
            'patient_last_name'   => 'Pérez',
            'service_date'        => now()->toDateString(),
            'procedures'          => [
                [
                    'medical_service_id' => $this->procedure->id,
                    'unit_price'         => 250000,
                    'quantity'           => 1,
                ],
            ],
        ], $overrides);
    }

    // ─── Crear ────────────────────────────────────────────────────────────────

    public function test_crear_orden_sin_descuentos(): void
    {
        Notification::fake();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $this->basePayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_status', 'approved');

        $this->assertDatabaseHas('patient_procedure_records', [
            'patient_document' => '1020304050',
            'discount_status'  => null,
        ]);
    }

    public function test_crear_orden_con_descuento_establece_estado_pendiente(): void
    {
        Notification::fake();

        $payload = $this->basePayload([
            'procedures' => [
                [
                    'medical_service_id' => $this->procedure->id,
                    'unit_price'         => 250000,
                    'quantity'           => 1,
                    'discount_type'      => 'percentage',
                    'discount_value'     => 10,
                ],
            ],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_status', 'discount_pending');

        $this->assertDatabaseHas('patient_procedure_records', [
            'patient_document' => '1020304050',
            'discount_status'  => 'pending',
            'discount_type'    => 'percentage',
        ]);
    }

    public function test_crear_orden_con_multiples_procedimientos(): void
    {
        Notification::fake();

        $procedure2 = MedicalServiceModel::where('type', 'procedure')->skip(1)->first();
        $procedure2 = $procedure2 ?? $this->procedure;

        $payload = $this->basePayload([
            'procedures' => [
                [
                    'medical_service_id' => $this->procedure->id,
                    'unit_price'         => 100000,
                    'quantity'           => 1,
                ],
                [
                    'medical_service_id' => $procedure2->id,
                    'unit_price'         => 200000,
                    'quantity'           => 2,
                ],
            ],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $orderNumber = $response->json('data.order_number');
        $this->assertNotNull($orderNumber);

        $this->assertDatabaseCount('patient_procedure_records',
            PatientProcedureRecordModel::where('order_number', $orderNumber)->count()
        );
    }

    public function test_registros_de_una_orden_comparten_order_number(): void
    {
        Notification::fake();

        $payload = $this->basePayload([
            'procedures' => [
                [
                    'medical_service_id' => $this->procedure->id,
                    'unit_price'         => 100000,
                    'quantity'           => 1,
                ],
                [
                    'medical_service_id' => $this->procedure->id,
                    'unit_price'         => 50000,
                    'quantity'           => 1,
                ],
            ],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $payload);

        $response->assertCreated();
        $orderNumber = $response->json('data.order_number');

        $count = PatientProcedureRecordModel::where('order_number', $orderNumber)->count();
        $this->assertSame(2, $count);
    }

    public function test_guarda_datos_de_contacto_del_paciente(): void
    {
        Notification::fake();

        $payload = $this->basePayload([
            'patient_email'   => 'paciente@example.com',
            'patient_address' => 'Calle 123',
            'patient_phone'   => '3001234567',
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('patient_procedure_records', [
            'patient_email'   => 'paciente@example.com',
            'patient_address' => 'Calle 123',
            'patient_phone'   => '3001234567',
        ]);
    }

    public function test_show_orden_retorna_vista_agrupada(): void
    {
        Notification::fake();

        $createResponse = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $this->basePayload());

        $orderNumber = $createResponse->json('data.order_number');

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/v1/service-orders/{$orderNumber}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_number', $orderNumber)
            ->assertJsonStructure([
                'data' => [
                    'order_number', 'patient_document', 'order_status',
                    'total_amount', 'total_discount', 'net_total', 'procedures',
                ],
            ]);
    }

    public function test_no_puede_crear_orden_sin_permiso(): void
    {
        $user = UserModel::create([
            'name'     => 'Sin Permisos',
            'email'    => 'noperms_order@sga.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $token = $user->createToken('test', [])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/service-orders', $this->basePayload())
            ->assertForbidden();
    }

    public function test_validacion_requiere_al_menos_un_procedimiento(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', [
                'patient_external_id' => 'EXT-001',
                'patient_document'    => '123',
                'patient_first_name'  => 'Test',
                'patient_last_name'   => 'User',
                'service_date'        => now()->toDateString(),
                'procedures'          => [],
            ]);

        $response->assertUnprocessable();
    }
}
