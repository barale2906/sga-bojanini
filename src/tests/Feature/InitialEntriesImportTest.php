<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InitialEntriesImportTest extends TestCase
{
    use RefreshDatabase;

    private array $auth;

    private WarehouseModel $warehouse;

    private ZoneModel $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->authenticateAsRole('super_administrador');
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->auth = $this->bearerAuthFor($admin);

        $this->warehouse = WarehouseModel::create([
            'name'      => 'Almacén Principal',
            'code'      => 'ALM-001',
            'is_active' => true,
        ]);

        $this->zone = ZoneModel::create([
            'warehouse_id' => $this->warehouse->id,
            'name'         => 'Zona General',
            'code'         => 'Z-GEN',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);
    }

    public function test_descarga_plantilla_devuelve_un_archivo_excel(): void
    {
        $response = $this->withHeaders($this->auth)
            ->get('/api/v1/movements/initial-entries/template');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type'),
        );
    }

    public function test_importa_entrada_simple_al_primer_almacen_y_primera_zona(): void
    {
        $product = $this->createProduct('PROD-A');
        $barcode = $product->genericProduct->barcode;

        $response = $this->withHeaders($this->auth)
            ->post('/api/v1/movements/initial-entries/import', [
                'file' => $this->makeEntriesUpload([
                    [$barcode, 'LOTE-001', 100, '2027-01-31', '', 'FAC-001', '4.5', 'Carga inicial'],
                ]),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertDatabaseHas('batches', [
            'product_variant_id' => $product->id,
            'lot_number'         => 'LOTE-001',
            'quantity_available' => 100,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $product->id,
            'warehouse_id'       => $this->warehouse->id,
            'movement_type'      => 'entry',
            'quantity'           => 100,
        ]);

        // El estante se generó dentro de la primera zona del primer almacén.
        $location = LocationModel::where('zone_id', $this->zone->id)->first();
        $this->assertNotNull($location);
        $this->assertEquals(3_000_000.0, (float) $location->volume_cm3);
        $this->assertEquals(500.0, (float) $location->max_weight_kg);
    }

    public function test_permite_elegir_el_almacen_destino_al_importar(): void
    {
        $product = $this->createProduct('PROD-B');
        $barcode = $product->genericProduct->barcode;

        $otherWarehouse = WarehouseModel::create([
            'name'      => 'Almacén Secundario',
            'code'      => 'ALM-002',
            'is_active' => true,
        ]);
        $otherZone = ZoneModel::create([
            'warehouse_id' => $otherWarehouse->id,
            'name'         => 'Zona Secundaria',
            'code'         => 'Z-SEC',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $response = $this->withHeaders($this->auth)
            ->post('/api/v1/movements/initial-entries/import', [
                'warehouse_id' => $otherWarehouse->id,
                'file'         => $this->makeEntriesUpload([
                    [$barcode, 'LOTE-002', 50, '2027-01-31', '', '', '', ''],
                ]),
            ]);

        $response->assertStatus(200)->assertJsonPath('data.success', 1);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $product->id,
            'warehouse_id'       => $otherWarehouse->id,
            'quantity'           => 50,
        ]);

        // No debe haber tocado el almacén/zona "por defecto" (el primero registrado).
        $this->assertDatabaseMissing('stock_movements', [
            'product_variant_id' => $product->id,
            'warehouse_id'       => $this->warehouse->id,
        ]);

        $this->assertNotNull(LocationModel::where('zone_id', $otherZone->id)->first());
    }

    public function test_rechaza_un_warehouse_id_inexistente(): void
    {
        $product = $this->createProduct('PROD-C');
        $barcode = $product->genericProduct->barcode;

        $response = $this->withHeaders($this->auth)
            ->post('/api/v1/movements/initial-entries/import', [
                'warehouse_id' => 999999,
                'file'         => $this->makeEntriesUpload([
                    [$barcode, 'LOTE-003', 10, '2027-01-31', '', '', '', ''],
                ]),
            ]);

        $response->assertStatus(422);
    }

    public function test_producto_con_cadena_de_frio_crea_zona_y_estante_automaticamente(): void
    {
        $product = $this->createProduct('PROD-FRIO', ['requires_cold_chain' => true]);
        $barcode = $product->genericProduct->barcode;

        $response = $this->withHeaders($this->auth)
            ->post('/api/v1/movements/initial-entries/import', [
                'file' => $this->makeEntriesUpload([
                    [$barcode, 'LOTE-FRIO-001', 10, '2027-01-31', '', '', '-18', ''],
                ]),
            ]);

        $response->assertStatus(200)->assertJsonPath('data.success', 1);

        $coldZone = ZoneModel::where('warehouse_id', $this->warehouse->id)
            ->where('type', 'cold')
            ->first();

        $this->assertNotNull($coldZone, 'Debe crearse automáticamente una zona de tipo cold.');

        $location = LocationModel::where('zone_id', $coldZone->id)->first();
        $this->assertNotNull($location, 'Debe crearse un estante dentro de la zona refrigerada.');
    }

    public function test_estante_se_genera_automaticamente_al_superar_capacidad(): void
    {
        // 1 m³ por unidad: 2 unidades = 2.000.000 cm³ (cabe en un estante de 3.000.000 cm³)
        $product = $this->createProduct('PROD-VOL', ['volume_cm3' => 1_000_000, 'weight_kg' => 0.001]);
        $barcode = $product->genericProduct->barcode;

        $response = $this->withHeaders($this->auth)
            ->post('/api/v1/movements/initial-entries/import', [
                'file' => $this->makeEntriesUpload([
                    [$barcode, 'LOTE-VOL-001', 2, '2027-01-31', '', '', '', ''],
                    [$barcode, 'LOTE-VOL-002', 2, '2027-01-31', '', '', '', ''],
                ]),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.success', 2)
            ->assertJsonPath('data.failed', 0);

        // La segunda fila no cabía en el primer estante (solo quedaba 1.000.000 cm³
        // libres) y debió generar un segundo estante con la misma capacidad estándar.
        $locations = LocationModel::where('zone_id', $this->zone->id)->orderBy('id')->get();
        $this->assertCount(2, $locations);

        foreach ($locations as $location) {
            $this->assertEquals(3_000_000.0, (float) $location->volume_cm3);
            $this->assertEquals(500.0, (float) $location->max_weight_kg);
        }
    }

    public function test_fila_con_producto_inexistente_se_reporta_como_fallida_sin_afectar_las_demas(): void
    {
        $product = $this->createProduct('PROD-OK');
        $barcode = $product->genericProduct->barcode;

        $response = $this->withHeaders($this->auth)
            ->post('/api/v1/movements/initial-entries/import', [
                'file' => $this->makeEntriesUpload([
                    [$barcode, 'LOTE-OK-001', 10, '2027-01-31', '', '', '', ''],
                    ['999999', 'LOTE-X', 5, '2027-01-31', '', '', '', ''],
                ]),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 1);

        $this->assertEquals(3, $response->json('data.errors.0.row'));
    }

    public function test_usuario_sin_permiso_no_puede_importar(): void
    {
        $product = $this->createProduct('PROD-A');
        $barcode = $product->genericProduct->barcode;
        $user    = $this->authenticateAsRole('personal_medico');

        $response = $this->withHeaders($this->bearerAuthFor($user))
            ->post('/api/v1/movements/initial-entries/import', [
                'file' => $this->makeEntriesUpload([
                    [$barcode, 'LOTE-001', 10, '2027-01-31', '', '', '', ''],
                ]),
            ]);

        $response->assertStatus(403);
    }

    private function createProduct(string $code, array $overrides = []): ProductVariantModel
    {
        $category = CategoryModel::where('code', 'INS-MED')->first();
        $unit     = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $maxBarcode = (int) GenericProductModel::max('barcode');
        $barcode    = str_pad((string) ($maxBarcode + 1), 6, '0', STR_PAD_LEFT);

        $generic = GenericProductModel::create(array_merge([
            'category_id'         => $category->id,
            'base_unit_id'        => $unit->id,
            'product_type'        => 'simple',
            'name'                => "Producto {$code}",
            'barcode'             => $barcode,
            'requires_cold_chain' => false,
            'is_active'           => true,
        ], $overrides));

        return ProductVariantModel::create([
            'generic_product_id' => $generic->id,
            'lab_brand'          => 'Proveedor Test',
            'is_active'          => true,
        ]);
    }

    /** @param  array<int, array{0: string, 1: string, 2: int, 3: string, 4: string, 5: string, 6: string, 7: string}>  $rows */
    private function makeEntriesUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Entradas');

        $headers = ['product_barcode', 'lot_number', 'quantity', 'expiration_date', 'manufacturing_date', 'invoice_number', 'entry_temperature', 'notes'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $col => $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $rowIndex + 2, $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'initial_entries_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'entradas.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
