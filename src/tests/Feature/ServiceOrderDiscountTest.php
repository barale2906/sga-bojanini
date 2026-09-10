<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests de integración para el flujo de aprobación de descuentos.
 */
class ServiceOrderDiscountTest extends TestCase
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

    private function createOrderWithDiscount(): string
    {
        Notification::fake();

        $payload = [
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
                    'discount_type'      => 'fixed',
                    'discount_value'     => 25000,
                ],
            ],
        ];

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $payload);

        return $response->json('data.order_number');
    }

    public function test_aprobar_descuento_actualiza_estado_y_registra_aprobador(): void
    {
        Notification::fake();

        $orderNumber = $this->createOrderWithDiscount();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/approve");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_status', 'approved');

        $this->assertDatabaseHas('patient_procedure_records', [
            'order_number'    => $orderNumber,
            'discount_status' => 'approved',
        ]);

        $record = PatientProcedureRecordModel::where('order_number', $orderNumber)->first();
        $this->assertNotNull($record->approved_by_user_id);
        $this->assertNotNull($record->approved_at);
    }

    public function test_aprobar_orden_ya_aprobada_retorna_409(): void
    {
        Notification::fake();

        $orderNumber = $this->createOrderWithDiscount();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/approve");

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/approve")
            ->assertStatus(409);
    }

    public function test_aprobar_orden_sin_descuentos_retorna_409(): void
    {
        Notification::fake();

        $payload = [
            'patient_external_id' => 'EXT-9002',
            'patient_document'    => '9988776655',
            'patient_first_name'  => 'María',
            'patient_last_name'   => 'García',
            'service_date'        => now()->toDateString(),
            'procedures'          => [
                [
                    'medical_service_id' => $this->procedure->id,
                    'unit_price'         => 100000,
                    'quantity'           => 1,
                ],
            ],
        ];

        $createResponse = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', $payload);

        $orderNumber = $createResponse->json('data.order_number');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/approve")
            ->assertStatus(409);
    }

    public function test_aprobar_sin_permiso_retorna_403(): void
    {
        Notification::fake();

        // Insert order record directly (avoid HTTP admin session leaking into restricted-user request)
        $orderNumber = 'OS-' . now()->format('Ymd') . '-000099';
        PatientProcedureRecordModel::create([
            'medical_service_id'  => $this->procedure->id,
            'patient_external_id' => 'EXT-9099',
            'patient_document'    => '9999999999',
            'patient_first_name'  => 'Test',
            'patient_last_name'   => 'Perm',
            'quantity'            => 1,
            'unit_price'          => 100000,
            'total'               => 100000,
            'service_date'        => now()->toDateString(),
            'order_number'        => $orderNumber,
            'discount_type'       => 'fixed',
            'discount_value'      => 10000,
            'discount_status'     => 'pending',
        ]);

        $user = UserModel::create([
            'name'      => 'Solo Ver',
            'email'     => 'solo_ver_disc@sga.test',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $token = $user->createToken('test', [])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/service-orders/{$orderNumber}/approve")
            ->assertForbidden();
    }

    public function test_listado_de_descuentos_solo_muestra_ordenes_pendientes(): void
    {
        Notification::fake();

        $orderNumber = $this->createOrderWithDiscount();

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders/discounts');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $orderNumbers = collect($response->json('data'))->pluck('order_number')->toArray();
        $this->assertContains($orderNumber, $orderNumbers);

        // Aprobar y verificar que desaparece
        Notification::fake();
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/approve");

        $response2 = $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders/discounts');

        $orderNumbers2 = collect($response2->json('data'))->pluck('order_number')->toArray();
        $this->assertNotContains($orderNumber, $orderNumbers2);
    }
}
