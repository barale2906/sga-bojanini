<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests de integración para el flujo de facturación y anulación de órdenes de servicio.
 */
class ServiceOrderBillingTest extends TestCase
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

    private function createOrder(): string
    {
        Notification::fake();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', [
                'patient_external_id' => 'EXT-8001',
                'patient_document'    => '1122334455',
                'patient_first_name'  => 'Laura',
                'patient_last_name'   => 'Gómez',
                'service_date'        => now()->toDateString(),
                'procedures'          => [
                    [
                        'medical_service_id' => $this->procedure->id,
                        'unit_price'         => 150000,
                        'quantity'           => 1,
                    ],
                ],
            ]);

        return $response->json('data.order_number');
    }

    // ─── Facturar ─────────────────────────────────────────────────────────────

    public function test_facturar_orden_cambia_billing_status_a_billed(): void
    {
        $orderNumber = $this->createOrder();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.billing_status', 'billed');

        $this->assertDatabaseHas('patient_procedure_records', [
            'order_number'   => $orderNumber,
            'billing_status' => 'billed',
        ]);

        $record = PatientProcedureRecordModel::where('order_number', $orderNumber)->first();
        $this->assertNotNull($record->billed_by_user_id);
        $this->assertNotNull($record->billed_at);
    }

    public function test_facturar_orden_ya_facturada_retorna_409(): void
    {
        $orderNumber = $this->createOrder();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill");

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill")
            ->assertStatus(409);
    }

    public function test_facturar_orden_anulada_retorna_409(): void
    {
        $orderNumber = $this->createOrder();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/cancel");

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill")
            ->assertStatus(409);
    }

    public function test_facturar_orden_inexistente_retorna_409(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders/OS-00000000-999999/bill')
            ->assertStatus(409);
    }

    // ─── Anular ───────────────────────────────────────────────────────────────

    public function test_anular_orden_cambia_billing_status_a_cancelled(): void
    {
        $orderNumber = $this->createOrder();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/cancel");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.billing_status', 'cancelled');

        $this->assertDatabaseHas('patient_procedure_records', [
            'order_number'   => $orderNumber,
            'billing_status' => 'cancelled',
        ]);

        $record = PatientProcedureRecordModel::where('order_number', $orderNumber)->first();
        $this->assertNotNull($record->billed_by_user_id);
        $this->assertNotNull($record->billed_at);
    }

    public function test_anular_orden_ya_anulada_retorna_409(): void
    {
        $orderNumber = $this->createOrder();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/cancel");

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/cancel")
            ->assertStatus(409);
    }

    public function test_anular_orden_facturada_es_posible(): void
    {
        $orderNumber = $this->createOrder();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill");

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.billing_status', 'cancelled');
    }

    public function test_anular_orden_inexistente_retorna_409(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders/OS-00000000-999999/cancel')
            ->assertStatus(409);
    }

    // ─── Permisos ─────────────────────────────────────────────────────────────

    public function test_facturar_sin_permiso_retorna_403(): void
    {
        // Creación directa en BD para no contaminar headers del cliente de test
        $orderNumber = 'OS-' . now()->format('Ymd') . '-BILL99';
        PatientProcedureRecordModel::create([
            'medical_service_id'  => $this->procedure->id,
            'patient_external_id' => 'EXT-PERM01',
            'patient_document'    => '7788990011',
            'patient_first_name'  => 'Test',
            'patient_last_name'   => 'Perm',
            'quantity'            => 1,
            'unit_price'          => 100000,
            'total'               => 100000,
            'service_date'        => now()->toDateString(),
            'order_number'        => $orderNumber,
        ]);

        $user = UserModel::create([
            'name'      => 'Sin Permiso Bill',
            'email'     => 'noperm_bill@sga.test',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $token = $user->createToken('test', [])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill")
            ->assertForbidden();
    }

    public function test_anular_sin_permiso_retorna_403(): void
    {
        // Creación directa en BD para no contaminar headers del cliente de test
        $orderNumber = 'OS-' . now()->format('Ymd') . '-CANC99';
        PatientProcedureRecordModel::create([
            'medical_service_id'  => $this->procedure->id,
            'patient_external_id' => 'EXT-PERM02',
            'patient_document'    => '1199887766',
            'patient_first_name'  => 'Test',
            'patient_last_name'   => 'Perm',
            'quantity'            => 1,
            'unit_price'          => 100000,
            'total'               => 100000,
            'service_date'        => now()->toDateString(),
            'order_number'        => $orderNumber,
        ]);

        $user = UserModel::create([
            'name'      => 'Sin Permiso Cancel',
            'email'     => 'noperm_cancel@sga.test',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $token = $user->createToken('test', [])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/service-orders/{$orderNumber}/cancel")
            ->assertForbidden();
    }

    // ─── Respuesta de show y listing reflejan billing_status ──────────────────

    public function test_show_orden_incluye_billing_status_nulo_por_defecto(): void
    {
        $orderNumber = $this->createOrder();

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/v1/service-orders/{$orderNumber}");

        $response->assertOk()
            ->assertJsonPath('data.billing_status', null);
    }

    public function test_show_orden_refleja_billing_status_tras_facturar(): void
    {
        $orderNumber = $this->createOrder();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill");

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/v1/service-orders/{$orderNumber}");

        $response->assertOk()
            ->assertJsonPath('data.billing_status', 'billed');
    }

    public function test_listado_puede_filtrar_por_billing_status_nulo(): void
    {
        $orderNumber = $this->createOrder();

        // Crear segunda orden y facturarla
        Notification::fake();
        $billed = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', [
                'patient_external_id' => 'EXT-8002',
                'patient_document'    => '9988776600',
                'patient_first_name'  => 'Ana',
                'patient_last_name'   => 'López',
                'service_date'        => now()->toDateString(),
                'procedures'          => [
                    [
                        'medical_service_id' => $this->procedure->id,
                        'unit_price'         => 80000,
                        'quantity'           => 1,
                    ],
                ],
            ])->json('data.order_number');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$billed}/bill");

        // Filtrar no facturadas (billing_status=null)
        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records?billing_status=null');

        $response->assertOk();

        $orderNumbers = collect($response->json('data'))->pluck('order_number')->toArray();
        $this->assertContains($orderNumber, $orderNumbers);
        $this->assertNotContains($billed, $orderNumbers);
    }

    public function test_listado_puede_filtrar_por_billing_status_billed(): void
    {
        $orderNumber = $this->createOrder();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill");

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records?billing_status=billed');

        $response->assertOk();

        $orderNumbers = collect($response->json('data'))->pluck('order_number')->toArray();
        $this->assertContains($orderNumber, $orderNumbers);
    }

    // ─── Todos los registros de una orden comparten el billing_status ──────────

    public function test_todos_los_registros_de_una_orden_quedan_facturados(): void
    {
        Notification::fake();

        $procedure2 = MedicalServiceModel::where('type', 'procedure')->skip(1)->first()
            ?? $this->procedure;

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', [
                'patient_external_id' => 'EXT-8003',
                'patient_document'    => '5544332211',
                'patient_first_name'  => 'Pedro',
                'patient_last_name'   => 'Ríos',
                'service_date'        => now()->toDateString(),
                'procedures'          => [
                    [
                        'medical_service_id' => $this->procedure->id,
                        'unit_price'         => 100000,
                        'quantity'           => 1,
                    ],
                    [
                        'medical_service_id' => $procedure2->id,
                        'unit_price'         => 200000,
                        'quantity'           => 1,
                    ],
                ],
            ]);

        $orderNumber = $response->json('data.order_number');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/service-orders/{$orderNumber}/bill");

        $count = PatientProcedureRecordModel::where('order_number', $orderNumber)
            ->where('billing_status', 'billed')
            ->count();

        $this->assertSame(
            PatientProcedureRecordModel::where('order_number', $orderNumber)->count(),
            $count,
        );
    }
}
