<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Actualizar un registro (PUT) no debe borrar lo que el cuerpo no trae: la
 * orden a la que pertenece, el descuento, quién lo creó, el contacto del
 * paciente ni su estado de facturación.
 */
class PatientProcedureRecordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private array $auth;
    private MedicalServiceModel $procedure;
    private PatientProcedureRecordModel $record;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $this->auth      = $this->bearerAuthFor(UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail());
        $this->procedure = MedicalServiceModel::where('type', 'procedure')->firstOrFail();

        $order = $this->postJson('/api/v1/service-orders', [
            'patient_external_id' => 'EXT-1',
            'patient_document'    => '1020304050',
            'patient_first_name'  => 'Carlos',
            'patient_last_name'   => 'Pérez',
            'patient_email'       => 'carlos@example.com',
            'patient_address'     => 'Calle 1 # 2-3',
            'patient_phone'       => '3000000000',
            'service_date'        => now()->toDateString(),
            'seller'              => 'Vendedor Uno',
            'referrer'            => 'Remitente Uno',
            'notes'               => 'Evolución inicial',
            'procedures'          => [[
                'medical_service_id' => $this->procedure->id,
                'unit_price'         => 1000,
                'quantity'           => 2,
                'discount_type'      => 'percentage',
                'discount_value'     => 10,
            ]],
        ], $this->auth)->assertCreated();

        $this->record = PatientProcedureRecordModel::where('order_number', $order->json('data.order_number'))->firstOrFail();
        $this->postJson("/api/v1/service-orders/{$this->record->order_number}/bill", [], $this->auth)->assertOk();
        $this->record->refresh();
    }

    /** Cuerpo mínimo: solo lo obligatorio, cambiando lo indicado. */
    private function body(array $overrides = []): array
    {
        return array_merge([
            'medical_service_id'  => $this->procedure->id,
            'patient_external_id' => 'EXT-1',
            'patient_document'    => '1020304050',
            'patient_first_name'  => 'Carlos',
            'patient_last_name'   => 'Pérez',
            'quantity'            => 2,
            'unit_price'          => 1000,
            'service_date'        => now()->toDateString(),
        ], $overrides);
    }

    private function update(array $overrides = []): void
    {
        $this->putJson("/api/v1/patient-procedure-records/{$this->record->id}", $this->body($overrides), $this->auth)
            ->assertOk();
    }

    public function test_actualizar_conserva_orden_descuento_creador_y_facturacion(): void
    {
        $before = $this->record->only([
            'order_number', 'created_by_user_id', 'discount_type', 'discount_value', 'discount_status',
            'discount_amount', 'net_total', 'billing_status', 'billed_by_user_id',
        ]);
        $this->assertNotNull($before['order_number']);
        $this->assertSame('billed', $before['billing_status']);

        $this->update();

        $after = $this->record->fresh();
        $this->assertSame($before['order_number'], $after->order_number);
        $this->assertSame($before['created_by_user_id'], $after->created_by_user_id);
        $this->assertSame($before['discount_type'], $after->discount_type);
        $this->assertEquals($before['discount_value'], $after->discount_value);
        $this->assertSame($before['discount_status'], $after->discount_status);
        $this->assertEquals($before['discount_amount'], $after->discount_amount);
        $this->assertEquals($before['net_total'], $after->net_total);
        $this->assertSame('billed', $after->billing_status);
        $this->assertSame($before['billed_by_user_id'], $after->billed_by_user_id);
    }

    public function test_actualizar_conserva_el_contacto_del_paciente(): void
    {
        $this->update();

        $after = $this->record->fresh();
        $this->assertSame('carlos@example.com', $after->patient_email);
        $this->assertSame('Calle 1 # 2-3', $after->patient_address);
        $this->assertSame('3000000000', $after->patient_phone);
    }

    public function test_los_campos_opcionales_ausentes_se_conservan(): void
    {
        $this->update();

        $after = $this->record->fresh();
        $this->assertSame('Vendedor Uno', $after->seller);
        $this->assertSame('Remitente Uno', $after->referrer);
        $this->assertSame('Evolución inicial', $after->notes);
        $this->assertTrue((bool) $after->is_active);
    }

    public function test_los_campos_opcionales_enviados_se_actualizan(): void
    {
        $this->update(['seller' => 'Vendedor Dos', 'referrer' => 'Remitente Dos', 'notes' => 'Nota nueva']);

        $after = $this->record->fresh();
        $this->assertSame('Vendedor Dos', $after->seller);
        $this->assertSame('Remitente Dos', $after->referrer);
        $this->assertSame('Nota nueva', $after->notes);
    }

    public function test_un_null_explicito_si_borra_el_campo(): void
    {
        $this->update(['seller' => null, 'notes' => null]);

        $after = $this->record->fresh();
        $this->assertNull($after->seller);
        $this->assertNull($after->notes);
        $this->assertSame('Remitente Uno', $after->referrer, 'el que no se envió se conserva');
    }

    public function test_desactivar_un_registro_funciona_y_no_activa_los_demas(): void
    {
        $this->update(['is_active' => false]);
        $this->assertFalse((bool) $this->record->fresh()->is_active);

        $this->update();
        $this->assertFalse((bool) $this->record->fresh()->is_active, 'omitir is_active no lo reactiva');
    }

    public function test_cambiar_cantidad_o_precio_recalcula_total_y_descuento(): void
    {
        $this->update(['quantity' => 4, 'unit_price' => 1000]);

        $after = $this->record->fresh();
        $this->assertEquals(4000, $after->total);
        $this->assertEquals(400, $after->discount_amount, '10 % de 4000');
        $this->assertEquals(3600, $after->net_total);
    }

    public function test_un_registro_sin_orden_sigue_actualizandose_igual(): void
    {
        $id = $this->postJson('/api/v1/patient-procedure-records', $this->body(['seller' => 'S']), $this->auth)
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/v1/patient-procedure-records/{$id}", $this->body(['quantity' => 3]), $this->auth)
            ->assertOk()
            ->assertJsonPath('data.quantity', 3)
            ->assertJsonPath('data.order_number', null);
    }
}
