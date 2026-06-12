<?php

namespace Tests\Feature;

use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ProcedurePriceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MedicalServiceImportTest extends TestCase
{
    use RefreshDatabase;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = $this->authenticateAsRole('super_administrador');
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $this->auth = $this->bearerAuthFor($admin);
    }

    private function auth(): array
    {
        return $this->auth;
    }

    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function makeSpreadsheetUpload(array $headers, array $rows, string $filename): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Servicios y Procedimientos');

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $col => $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $rowIndex + 2, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'import_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_descarga_plantilla_servicios_medicos(): void
    {
        $response = $this->withHeaders($this->auth())
            ->get('/api/v1/import/medical-services/template');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
    }

    public function test_administrador_no_puede_importar_servicios_medicos(): void
    {
        $admin = $this->authenticateAsRole('administrador');
        $auth = $this->bearerAuthFor($admin);

        $headers = ['type', 'code', 'name', 'parent_code', 'description', 'is_active', 'unit_price', 'effective_from', 'effective_to', 'notes'];
        $rows = [
            ['service', 'SERV-NOPE', 'Servicio No Autorizado', '', '', 'TRUE', '', '', '', ''],
        ];
        $file = $this->makeSpreadsheetUpload($headers, $rows, 'servicios.xlsx');

        $this->withHeaders($auth)
            ->post('/api/v1/import/medical-services', ['file' => $file])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "No tienes el permiso 'servicios_medicos.importar' para realizar esta acción.");

        $this->withHeaders($auth)
            ->get('/api/v1/import/medical-services/template')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "No tienes el permiso 'servicios_medicos.importar' para realizar esta acción.");

        $this->assertFalse(MedicalServiceModel::where('code', 'SERV-NOPE')->exists());
    }

    public function test_importar_servicios_y_procedimientos(): void
    {
        $headers = ['type', 'code', 'name', 'parent_code', 'description', 'is_active', 'unit_price', 'effective_from', 'effective_to', 'notes'];

        $rows = [
            // Fila 2: nuevo servicio
            ['service', 'SERV-IMP', 'Servicio Importado', '', 'Servicio creado por importación', 'TRUE', '', '', '', ''],
            // Fila 3: procedimiento que pertenece al servicio importado en la fila 2
            ['procedure', 'PROC-IMP-1', 'Procedimiento Importado 1', 'SERV-IMP', 'Procedimiento creado por importación', 'TRUE', '75000', '2026-01-01', '', 'Tarifa inicial'],
            // Fila 4: procedimiento que pertenece a un servicio existente (CONS, del CostCenterSeeder)
            ['procedure', 'PROC-IMP-2', 'Procedimiento Importado 2', 'CONS', '', 'TRUE', '30000', '2026-01-01', '2026-12-31', ''],
            // Fila 5: procedimiento con servicio padre inexistente -> error
            ['procedure', 'PROC-IMP-3', 'Procedimiento Inválido', 'NO-EXISTE', '', 'TRUE', '10000', '2026-01-01', '', ''],
            // Fila completamente vacía: debe ser ignorada
            ['', '', '', '', '', '', '', '', '', ''],
        ];

        $file = $this->makeSpreadsheetUpload($headers, $rows, 'servicios.xlsx');

        $response = $this->withHeaders($this->auth())
            ->post('/api/v1/import/medical-services', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.success', 3)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.errors.0.row', 5)
            ->assertJsonPath('data.errors.0.errors.parent_code.0', 'No existe ningún servicio con este código. Revise la hoja "Servicios existentes" de la plantilla.');

        $service = MedicalServiceModel::where('code', 'SERV-IMP')->first();
        $this->assertNotNull($service);
        $this->assertSame('service', $service->type);
        $this->assertNull($service->parent_id);

        $procedure1 = MedicalServiceModel::where('code', 'PROC-IMP-1')->first();
        $this->assertNotNull($procedure1);
        $this->assertSame('procedure', $procedure1->type);
        $this->assertSame($service->id, $procedure1->parent_id);

        $price1 = ProcedurePriceModel::where('medical_service_id', $procedure1->id)->first();
        $this->assertNotNull($price1);
        $this->assertEquals(75000, $price1->unit_price);
        $this->assertSame('2026-01-01', $price1->effective_from->format('Y-m-d'));
        $this->assertSame('Tarifa inicial', $price1->notes);

        $procedure2 = MedicalServiceModel::where('code', 'PROC-IMP-2')->first();
        $this->assertNotNull($procedure2);
        $consService = MedicalServiceModel::where('code', 'CONS')->first();
        $this->assertSame($consService->id, $procedure2->parent_id);

        $price2 = ProcedurePriceModel::where('medical_service_id', $procedure2->id)->first();
        $this->assertSame('2026-12-31', $price2->effective_to->format('Y-m-d'));

        $this->assertFalse(MedicalServiceModel::where('code', 'PROC-IMP-3')->exists());
    }
}
