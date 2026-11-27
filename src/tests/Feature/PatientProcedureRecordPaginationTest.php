<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /patient-procedure-records: paginación opcional (retrocompatible).
 */
class PatientProcedureRecordPaginationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private int $procedureId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $this->procedureId = MedicalServiceModel::where('type', 'procedure')->first()->id;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function createRecords(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->withHeaders($this->auth())
                ->postJson('/api/v1/patient-procedure-records', [
                    'medical_service_id'  => $this->procedureId,
                    'patient_external_id' => "EXT-{$i}",
                    'patient_document'    => (string) (7000 + $i),
                    'patient_first_name'  => 'Paciente',
                    'patient_last_name'   => "Número {$i}",
                    'quantity'            => 1,
                    'unit_price'          => 1000,
                    'service_date'        => now()->toDateString(),
                ])
                ->assertStatus(201);
        }
    }

    public function test_sin_page_ni_per_page_devuelve_todos_sin_meta(): void
    {
        $this->createRecords(5);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records')
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $this->assertArrayNotHasKey('meta', $response->json());
    }

    public function test_con_per_page_pagina_y_devuelve_meta(): void
    {
        $this->createRecords(5);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_paginar_respeta_los_filtros_y_cada_registro_conserva_su_forma(): void
    {
        $this->createRecords(4);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records?page=1&patient_document=7002')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.patient_document', '7002')
            ->assertJsonStructure(['data' => [['id', 'patient_document', 'quantity', 'unit_price', 'total']]]);
    }

    public function test_per_page_se_limita_al_maximo_configurado(): void
    {
        $this->createRecords(1);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', config('sga.pagination.max_per_page', 100));
    }

    public function test_rechaza_parametros_de_paginacion_invalidos(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/patient-procedure-records?per_page=0&page=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page', 'page']);
    }
}
