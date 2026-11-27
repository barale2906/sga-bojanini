<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * GET /service-orders: listado paginado de órdenes de servicio.
 */
class ServiceOrderListTest extends TestCase
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

    private function createOrder(string $document, string $date, int $lines = 1): string
    {
        Notification::fake();

        $procedures = array_fill(0, $lines, [
            'medical_service_id' => $this->procedure->id,
            'unit_price'         => 100000,
            'quantity'           => 1,
        ]);

        return $this->withHeaders($this->auth())
            ->postJson('/api/v1/service-orders', [
                'patient_external_id' => "EXT-{$document}",
                'patient_document'    => $document,
                'patient_first_name'  => 'Laura',
                'patient_last_name'   => 'Gómez',
                'service_date'        => $date,
                'procedures'          => $procedures,
            ])
            ->json('data.order_number');
    }

    public function test_lista_ordenes_paginadas_con_la_mas_reciente_primero(): void
    {
        $old    = $this->createOrder('1001', '2026-09-01');
        $recent = $this->createOrder('1002', '2026-09-20', 2);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('data.0.order_number', $recent)
            ->assertJsonCount(2, 'data.0.procedures')
            ->assertJsonPath('data.0.net_total', 200000)
            ->assertJsonPath('data.1.order_number', $old);
    }

    public function test_pagina_por_orden_y_no_por_registro(): void
    {
        $this->createOrder('1001', '2026-09-01', 3);
        $this->createOrder('1002', '2026-09-02', 3);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(3, 'data.0.procedures');
    }

    public function test_filtra_por_documento_fechas_y_estado_de_facturacion(): void
    {
        $a = $this->createOrder('55501', '2026-09-01');
        $this->createOrder('99902', '2026-09-15');

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders?patient_document=555')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_number', $a);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders?service_date_from=2026-09-10&service_date_to=2026-09-30')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withHeaders($this->auth())->postJson("/api/v1/service-orders/{$a}/bill")->assertOk();

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders?billing_status=billed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.order_number', $a);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders?billing_status=null')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_rechaza_filtros_invalidos(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders?billing_status=otro&service_date_from=ayer')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['billing_status', 'service_date_from']);
    }

    public function test_requiere_permiso_de_ver_ordenes(): void
    {
        $user = UserModel::create([
            'name'      => 'Sin permisos',
            'email'     => 'sin_permisos_lista@sga.test',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $token = $user->createToken('test', [])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/service-orders')
            ->assertForbidden();
    }

    public function test_no_choca_con_la_ruta_de_descuentos_pendientes(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/service-orders/discounts')
            ->assertOk();
    }
}
