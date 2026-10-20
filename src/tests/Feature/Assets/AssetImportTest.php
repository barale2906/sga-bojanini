<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Application\Import\AssetImportLayout;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetPlaceModel;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Plantilla descargable e importación masiva de activos. */
class AssetImportTest extends AssetTestCase
{
    /**
     * Construye un .xlsx con las hojas indicadas: [nombre => [encabezados, filas...]].
     */
    private function excel(array $sheets): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($sheets as $title => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $sheet->fromArray($rows, null, 'A1', true);
        }

        $path = tempnam(sys_get_temp_dir(), 'assets_import_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'activos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** Fila de la hoja Activos a partir de un mapa columna => valor. */
    private function assetRow(array $values): array
    {
        return array_map(fn ($header) => $values[$header] ?? null, AssetImportLayout::headers(AssetImportLayout::ASSET_COLUMNS));
    }

    public function test_descarga_plantilla_con_hojas_y_catalogos_vigentes(): void
    {
        $this->place('Clínica', 'Consultorio 1');

        $response = $this->withHeaders($this->auth())->get('/api/v1/assets/import/template')->assertOk();

        $path = $response->baseResponse->getFile()->getPathname();
        $book = IOFactory::load($path);

        $this->assertSame(
            ['Activos', 'Hoja de vida biomédica', 'Hoja de vida cómputo', 'Categorías', 'Sedes y sitios', 'Instrucciones'],
            $book->getSheetNames(),
        );
        $this->assertSame('name', $book->getSheetByName('Activos')->getCell('A1')->getValue());
        $this->assertSame('Clínica', $book->getSheetByName('Sedes y sitios')->getCell('A2')->getValue());
    }

    public function test_importa_activos_catalogos_planes_y_hojas_de_vida(): void
    {
        $headers = AssetImportLayout::headers(AssetImportLayout::ASSET_COLUMNS);

        $file = $this->excel([
            'Activos' => [
                $headers,
                AssetImportLayout::labels(AssetImportLayout::ASSET_COLUMNS),
                $this->assetRow([
                    'name' => 'Electrocardiógrafo', 'category_code' => 'BIO', 'site_name' => 'Sede Norte', 'place_name' => 'Cardiología',
                    'serial' => 458722, 'acquisition_date' => '2024-03-15', 'status' => 'operational', 'comment' => 'Carga inicial',
                    'maintenance_type' => 'preventive', 'frequency_value' => 6, 'frequency_unit' => 'months',
                    'performed_by_type' => 'external', 'last_maintenance_date' => now()->subMonths(2)->toDateString(),
                ]),
                $this->assetRow([
                    'name' => 'PC Recepción', 'category_code' => 'COM', 'site_name' => 'Clínica', 'place_name' => 'Recepción', 'serial' => 'PC-01',
                ]),
                $this->assetRow(['name' => 'Escritorio', 'category_code' => 'LAB', 'site_name' => 'Clínica', 'place_name' => 'Recepción']),
                $this->assetRow(['name' => 'Silla', 'category_code' => 'MUE', 'site_name' => 'Clínica', 'place_name' => 'Recepción', 'maintenance_type' => 'preventive', 'frequency_value' => 1, 'frequency_unit' => 'months', 'performed_by_type' => 'internal']),
                $this->assetRow(['category_code' => 'XYZ']),
            ],
            'Hoja de vida biomédica' => [
                AssetImportLayout::headers(AssetImportLayout::biomedicalColumns()),
                ['458722', 'Mindray', 'China', 2023, 10, 'purchase', 'Cables', '2019DM-1', 'IIb', 'diagnosis', 'electric', '110 V', '1 A', '60 W', '60 Hz', 'TRUE'],
                ['NO-EXISTE'],
            ],
            'Hoja de vida cómputo' => [
                AssetImportLayout::headers(AssetImportLayout::computingColumns()),
                ['PC-01', 'Lenovo', null, null, null, null, null, 'Intel i5', '16 GB', 'SSD 512', 'Windows 11', '192.168.1.10', '00:1A:2B:3C:4D:5E', 'REC-01'],
            ],
            'Categorías' => [
                ['code', 'name', 'asset_type', 'description'],
                ['BIO', 'Equipo biomédico', 'biomedical', null],
                ['LAB', 'Laboratorio', 'biomedical', 'Nueva desde el archivo'],
            ],
        ]);

        $result = $this->withHeaders($this->auth())
            ->post('/api/v1/assets/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data');

        $this->assertSame(5, $result['total']);
        $this->assertSame(3, $result['created']);
        $this->assertSame(2, $result['failed']);
        $this->assertSame(['created' => 1, 'skipped' => 1], array_intersect_key($result['related_catalogs']['categories'], ['created' => 0, 'skipped' => 0]));
        $this->assertSame(2, $result['technical_profiles']['created']);
        $this->assertSame(1, $result['technical_profiles']['failed']);

        $failedRows = array_column($result['errors'], 'row');
        $this->assertSame([6, 7], $failedRows);

        $ecg = AssetModel::where('serial', '458722')->firstOrFail();
        $this->assertSame('000001', $ecg->code);
        $this->assertSame('Sede Norte → Cardiología', $ecg->currentPlace->load('site')->fullName());
        $this->assertSame('IIb', $ecg->technicalProfile->risk_class);
        $this->assertTrue($ecg->technicalProfile->requires_calibration);
        $this->assertDatabaseHas('asset_movements', ['asset_id' => $ecg->id, 'type' => 'import']);
        $this->assertDatabaseHas('asset_comments', ['asset_id' => $ecg->id, 'comment' => 'Carga inicial']);

        $next = AssetMaintenanceModel::where('asset_id', $ecg->id)->where('status', 'scheduled')->firstOrFail();
        $this->assertSame(now()->subMonths(2)->addMonths(6)->toDateString(), $next->scheduled_date->toDateString());

        $this->assertSame('REC-01', AssetModel::where('serial', 'PC-01')->first()->technicalProfile->hostname);
        $this->assertSame(1, AssetPlaceModel::where('name', 'Recepción')->count());
        $this->assertDatabaseHas('import_logs', ['entity_type' => 'assets', 'total_rows' => 5, 'success_rows' => 3]);
    }

    public function test_importar_requiere_permiso(): void
    {
        $viewer = $this->userWith(['activos.ver']);

        $this->withHeaders($this->auth($viewer))->get('/api/v1/assets/import/template')->assertForbidden();
    }
}
