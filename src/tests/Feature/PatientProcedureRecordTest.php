<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de integración para el endpoint /api/v1/patient-procedure-records.
 *
 * Cubre: CRUD, validaciones, historial por paciente, campos de nombre del paciente
 * y que `notes` no se exponga en los endpoints de listado/detalle.
 */
class PatientProcedureRecordTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    /** @var array<string, mixed> Datos base válidos para crear un registro */
    private array $basePayload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin       = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $procedure = MedicalServiceModel::where('type', 'procedure')->first();

        $this->basePayload = [
            'medical_service_id'  => $procedure->id,
            'patient_external_id' => 'EXT-9001',
            'patient_document'    => '1020304050',
            'patient_first_name'  => 'Carlos Andrés',
            'patient_last_name'   => 'Pérez Gómez',
            'quantity'            => 1,
            'unit_price'          => 250000,
            'service_date'        => now()->toDateString(),
        ];
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── Crear ────────────────────────────────────────────────────────────────

    public function test_crear_registro_persiste_nombres_del_paciente(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', $this->basePayload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.patient_first_name', 'Carlos Andrés')
            ->assertJsonPath('data.patient_last_name', 'Pérez Gómez')
            ->assertJsonPath('data.patient_document', '1020304050')
            ->assertJsonPath('data.patient_external_id', 'EXT-9001');

        $this->assertDatabaseHas('patient_procedure_records', [
            'patient_external_id' => 'EXT-9001',
            'patient_first_name'  => 'Carlos Andrés',
            'patient_last_name'   => 'Pérez Gómez',
        ]);
    }

    public function test_crear_registro_calcula_total_correctamente(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
                'quantity'   => 3,
                'unit_price' => 100000,
            ]))
            ->assertStatus(201)
            ->assertJson(['data' => ['total' => 300000]]);
    }

    public function test_crear_registro_con_notas_no_expone_notas_en_respuesta(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
                'notes' => 'Nota interna confidencial',
            ]));

        $response->assertStatus(201);

        $this->assertArrayNotHasKey('notes', $response->json('data'));
    }

    // ─── Validaciones ─────────────────────────────────────────────────────────

    public function test_crear_sin_patient_first_name_retorna_422(): void
    {
        $payload = $this->basePayload;
        unset($payload['patient_first_name']);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_first_name']);
    }

    public function test_crear_sin_patient_last_name_retorna_422(): void
    {
        $payload = $this->basePayload;
        unset($payload['patient_last_name']);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_last_name']);
    }

    public function test_crear_con_fecha_futura_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
                'service_date' => now()->addDay()->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_date']);
    }

    public function test_crear_con_cantidad_cero_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
                'quantity' => 0,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_crear_con_servicio_padre_retorna_409(): void
    {
        $service = MedicalServiceModel::where('type', 'service')->first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
                'medical_service_id' => $service->id,
            ]))
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_crear_con_precio_negativo_retorna_409(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
                'unit_price' => -100,
            ]))
            ->assertUnprocessable();
    }

    // ─── Listar ───────────────────────────────────────────────────────────────

    public function test_listar_incluye_nombres_del_paciente_y_nombre_del_procedimiento(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', $this->basePayload);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $first = $response->json('data.0');

        $this->assertArrayHasKey('patient_first_name', $first);
        $this->assertArrayHasKey('patient_last_name', $first);
        $this->assertArrayHasKey('medical_service_name', $first);
        $this->assertArrayNotHasKey('notes', $first);
        $this->assertEquals('Carlos Andrés', $first['patient_first_name']);
        $this->assertEquals('Pérez Gómez', $first['patient_last_name']);
        $this->assertNotNull($first['medical_service_name']);
    }

    public function test_listar_filtra_por_patient_document(): void
    {
        $this->withHeaders($this->auth())->postJson('/api/v1/patient-procedure-records', $this->basePayload);
        $this->withHeaders($this->auth())->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
            'patient_external_id' => 'EXT-9002',
            'patient_document'    => '9999999999',
            'patient_first_name'  => 'María',
            'patient_last_name'   => 'López',
        ]));

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records?patient_document=1020304050')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ─── Actualizar ───────────────────────────────────────────────────────────

    public function test_actualizar_registro_persiste_nuevos_nombres(): void
    {
        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', $this->basePayload);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->putJson("/api/v1/patient-procedure-records/{$id}", array_merge($this->basePayload, [
                'patient_first_name' => 'Juan Pablo',
                'patient_last_name'  => 'Rodríguez Díaz',
            ]))
            ->assertOk()
            ->assertJsonPath('data.patient_first_name', 'Juan Pablo')
            ->assertJsonPath('data.patient_last_name', 'Rodríguez Díaz');

        $this->assertDatabaseHas('patient_procedure_records', [
            'id'                 => $id,
            'patient_first_name' => 'Juan Pablo',
            'patient_last_name'  => 'Rodríguez Díaz',
        ]);
    }

    public function test_actualizar_registro_inexistente_retorna_409(): void
    {
        $this->withHeaders($this->auth())
            ->putJson('/api/v1/patient-procedure-records/99999', $this->basePayload)
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ─── Eliminar ─────────────────────────────────────────────────────────────

    public function test_eliminar_registro_hace_soft_delete(): void
    {
        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/patient-procedure-records', $this->basePayload);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/patient-procedure-records/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('patient_procedure_records', ['id' => $id]);
    }

    // ─── Historial por paciente ───────────────────────────────────────────────

    public function test_historial_incluye_nombres_del_paciente_y_codigo_procedimiento(): void
    {
        $procedure = MedicalServiceModel::where('type', 'procedure')->first();

        $this->withHeaders($this->auth())->postJson('/api/v1/patient-procedure-records', $this->basePayload);
        $this->withHeaders($this->auth())->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
            'unit_price' => 180000,
        ]));

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/patients/EXT-9001/procedure-records');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.patient_external_id', 'EXT-9001')
            ->assertJsonPath('data.patient_first_name', 'Carlos Andrés')
            ->assertJsonPath('data.patient_last_name', 'Pérez Gómez')
            ->assertJsonPath('data.summary.total_records', 2);

        $firstRecord = $response->json('data.records.0');
        $this->assertArrayHasKey('procedure_code', $firstRecord);
        $this->assertArrayHasKey('procedure_name', $firstRecord);
        $this->assertEquals($procedure->code, $firstRecord['procedure_code']);
    }

    public function test_historial_paciente_sin_registros_retorna_resumen_vacio(): void
    {
        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/patients/NO-EXISTE/procedure-records');

        $response->assertOk()
            ->assertJsonPath('data.summary.total_records', 0)
            ->assertJson(['data' => ['summary' => ['total_amount' => 0]]])
            ->assertJsonPath('data.summary.first_service_date', null)
            ->assertJsonPath('data.patient_first_name', null);
    }

    public function test_historial_calcula_total_amount_correctamente(): void
    {
        $this->withHeaders($this->auth())->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
            'quantity' => 2, 'unit_price' => 100000,
        ]));
        $this->withHeaders($this->auth())->postJson('/api/v1/patient-procedure-records', array_merge($this->basePayload, [
            'quantity' => 1, 'unit_price' => 50000,
        ]));

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/patients/EXT-9001/procedure-records')
            ->assertOk()
            ->assertJson(['data' => ['summary' => ['total_amount' => 250000]]]);
    }
}
