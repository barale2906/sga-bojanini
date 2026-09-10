<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ProcedurePriceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Tests de integración para endpoints de listas de precios.
 */
class PriceListTest extends TestCase
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

    private function makeExcelFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'procedure_code');
        $sheet->setCellValue('B1', 'procedure_name');
        $sheet->setCellValue('C1', 'service_name');
        $sheet->setCellValue('D1', 'unit_price');

        $row = 2;
        foreach ($rows as $r) {
            $sheet->setCellValue("A{$row}", $r[0]);
            $sheet->setCellValue("B{$row}", $r[1]);
            $sheet->setCellValue("C{$row}", $r[2]);
            $sheet->setCellValue("D{$row}", $r[3]);
            $row++;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'price_list_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return new UploadedFile($tmpPath, 'price_list.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_descargar_plantilla_retorna_excel(): void
    {
        $response = $this->withHeaders($this->auth())
            ->get('/api/v1/price-lists/template');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_importar_desactiva_precios_anteriores_y_crea_nuevos(): void
    {
        $procedure = MedicalServiceModel::where('type', 'procedure')->first();

        ProcedurePriceModel::create([
            'medical_service_id' => $procedure->id,
            'unit_price'         => 50000,
            'effective_from'     => now()->toDateString(),
            'is_active'          => true,
        ]);

        $file = $this->makeExcelFile([
            [$procedure->code, $procedure->name, '', 80000],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/price-lists/import', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.processed', 1);

        $this->assertDatabaseHas('procedure_prices', [
            'medical_service_id' => $procedure->id,
            'unit_price'         => 80000,
            'is_active'          => true,
        ]);

        $this->assertDatabaseMissing('procedure_prices', [
            'medical_service_id' => $procedure->id,
            'unit_price'         => 50000,
            'is_active'          => true,
        ]);
    }

    public function test_importar_con_codigo_invalido_registra_en_skipped(): void
    {
        $file = $this->makeExcelFile([
            ['CODIGO-INEXISTENTE', 'Nombre', '', 10000],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/price-lists/import', ['file' => $file]);

        $response->assertStatus(409);
    }

    public function test_importar_sin_permiso_retorna_403(): void
    {
        $user = UserModel::create([
            'name'      => 'Sin Permisos',
            'email'     => 'no_perm_price@sga.test',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $token = $user->createToken('test', [])->plainTextToken;

        $file = $this->makeExcelFile([['X', 'Y', 'Z', 1000]]);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/price-lists/import', ['file' => $file])
            ->assertForbidden();
    }

    public function test_precio_vigente_retorna_precio_activo(): void
    {
        $procedure = MedicalServiceModel::where('type', 'procedure')->first();

        ProcedurePriceModel::create([
            'medical_service_id' => $procedure->id,
            'unit_price'         => 120000,
            'effective_from'     => now()->toDateString(),
            'is_active'          => true,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/v1/procedures/{$procedure->id}/current-price");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unit_price', 120000);
    }
}
