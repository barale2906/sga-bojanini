<?php

namespace Tests\Feature;

use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductSanitaryRegistrationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private array $auth;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = $this->authenticateAsRole('super_administrador');
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

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
    private function makeSpreadsheetUpload(array $headers, array $rows, string $filename, string $sheetTitle = 'Productos'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

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

    /**
     * @param  array<string, array{headers: string[], rows: array<int, array<int, mixed>>}>  $sheets
     */
    private function makeMultiSheetUpload(array $sheets, string $filename): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $first = true;

        foreach ($sheets as $title => $sheetData) {
            $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $first = false;
            $sheet->setTitle($title);

            foreach ($sheetData['headers'] as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            }

            foreach ($sheetData['rows'] as $rowIndex => $row) {
                foreach ($row as $col => $value) {
                    $sheet->setCellValueByColumnAndRow($col + 1, $rowIndex + 2, $value);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'import_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_descarga_plantilla_productos(): void
    {
        $response = $this->withHeaders($this->auth())
            ->get('/api/v1/import/templates/products');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
    }

    public function test_descarga_plantilla_proveedores(): void
    {
        $response = $this->withHeaders($this->auth())
            ->get('/api/v1/import/templates/suppliers');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('content-type'),
        );
    }

    public function test_descarga_plantilla_entidad_invalida_404(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/import/templates/invalid')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_importar_productos_con_filas_validas_e_invalidas(): void
    {
        $headers = [
            'name', 'code', 'sku', 'category_code', 'unit_abbreviation', 'classification_code',
            'description', 'requires_cold_chain', 'reorder_point', 'reorder_quantity', 'min_stock', 'max_stock',
        ];

        $rows = [
            // Fila válida (Excel fila 2)
            ['Producto Importado', 'IMP-001', 'SKU-IMP-001', 'INS-MED', 'UND', 'DM', 'Descripción', 'TRUE', 10, 20, 5, 100],
            // Fila con categoría inexistente (Excel fila 3)
            ['Producto Invalido', 'IMP-002', 'SKU-IMP-002', 'CAT-NO-EXISTE', 'UND', '', '', 'FALSE', 0, 0, 0, 0],
            // Fila completamente vacía: debe ser ignorada
            ['', '', '', '', '', '', '', '', '', '', '', ''],
        ];

        $file = $this->makeSpreadsheetUpload($headers, $rows, 'productos.xlsx');

        $response = $this->withHeaders($this->auth())
            ->post('/api/v1/import/products', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.errors.0.row', 3)
            ->assertJsonPath('data.errors.0.errors.category_code.0', 'No existe ninguna categoría con este código. Revise la hoja "Categorías" de la plantilla.');

        $product = ProductModel::where('code', 'IMP-001')->first();
        $this->assertNotNull($product);
        $this->assertTrue($product->requires_cold_chain);
        $this->assertTrue($product->is_active);
        $this->assertSame('simple', $product->product_type);
        $this->assertSame(
            ProductClassificationModel::where('code', 'DM')->first()->id,
            $product->classification_id,
        );

        $this->assertFalse(ProductModel::where('code', 'IMP-002')->exists());
    }

    public function test_importar_producto_con_registro_sanitario(): void
    {
        $headers = [
            'name', 'code', 'category_code', 'unit_abbreviation', 'classification_code', 'sanitary_registration',
        ];

        $rows = [
            // Con clasificación DM y registro sanitario
            ['Producto DM con INVIMA',  'IMP-SR-001', 'INS-MED', 'UND', 'DM',  '2024DM-0099999'],
            // Sin clasificación pero con registro sanitario: debe importarse igual
            ['Producto sin clasif',     'IMP-SR-002', 'INS-MED', 'UND', '',    '2024MED-0001'],
            // Sin registro sanitario
            ['Producto sin INVIMA',     'IMP-SR-003', 'INS-MED', 'UND', 'MED', ''],
        ];

        $file = $this->makeSpreadsheetUpload($headers, $rows, 'productos_sr.xlsx');

        $response = $this->withHeaders($this->auth())
            ->post('/api/v1/import/products', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.success', 3)
            ->assertJsonPath('data.failed', 0);

        // Producto 1: DM + registro sanitario
        $p1 = ProductModel::where('code', 'IMP-SR-001')->first();
        $this->assertNotNull($p1);
        $this->assertSame(ProductClassificationModel::where('code', 'DM')->first()->id, $p1->classification_id);
        $sr1 = ProductSanitaryRegistrationModel::where('product_id', $p1->id)->first();
        $this->assertNotNull($sr1);
        $this->assertSame('2024DM-0099999', $sr1->registration_number);
        $this->assertSame('2099-12-31', $sr1->expiry_date->format('Y-m-d'));
        $this->assertTrue($sr1->is_active);

        // Producto 2: sin clasificación pero con registro sanitario → importa y crea el registro
        $p2 = ProductModel::where('code', 'IMP-SR-002')->first();
        $this->assertNotNull($p2);
        $this->assertNull($p2->classification_id);
        $sr2 = ProductSanitaryRegistrationModel::where('product_id', $p2->id)->first();
        $this->assertNotNull($sr2);
        $this->assertSame('2024MED-0001', $sr2->registration_number);

        // Producto 3: MED sin registro sanitario → importa sin crear registro
        $p3 = ProductModel::where('code', 'IMP-SR-003')->first();
        $this->assertNotNull($p3);
        $this->assertFalse(ProductSanitaryRegistrationModel::where('product_id', $p3->id)->exists());
    }

    public function test_importar_proveedores(): void
    {
        $headers = ['name', 'tax_id', 'contact_name', 'phone', 'email', 'address', 'notes'];

        $rows = [
            ['Proveedor Importado', '900999999-1', 'Juan Pérez', '3001234567', 'contacto@proveedor.com', 'Calle 1', 'Notas'],
            ['Proveedor sin email valido', '', '', '', 'correo-invalido', '', ''],
        ];

        $file = $this->makeSpreadsheetUpload($headers, $rows, 'proveedores.xlsx', 'Proveedores');

        $response = $this->withHeaders($this->auth())
            ->post('/api/v1/import/suppliers', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.errors.0.row', 3)
            ->assertJsonPath('data.errors.0.errors.email.0', 'El correo electrónico no tiene un formato válido.');

        $this->assertTrue(SupplierModel::where('tax_id', '900999999-1')->exists());
    }

    public function test_importar_productos_crea_categorias_unidades_y_clasificaciones_nuevas(): void
    {
        $productosHeaders = [
            'name', 'code', 'sku', 'category_code', 'unit_abbreviation', 'classification_code',
            'description', 'requires_cold_chain', 'reorder_point', 'reorder_quantity', 'min_stock', 'max_stock',
        ];

        $productosRows = [
            ['Producto Nuevo', 'IMP-010', 'SKU-IMP-010', 'CAT-NUEVA', 'NU', 'CLS-NUEVA', 'Descripción', 'FALSE', 0, 0, 0, 0],
        ];

        $categoriasHeaders = ['code', 'name', 'parent_code', 'description'];
        $categoriasRows = [
            ['INS-MED', 'Insumos Médicos', '', ''], // ya existe -> se omite
            ['CAT-NUEVA', 'Categoría Nueva', 'INS-MED', 'Categoría creada por importación'],
            ['CAT-INVALIDA', '', '', ''], // sin name -> error
        ];

        $unidadesHeaders = ['abbreviation', 'name', 'is_base'];
        $unidadesRows = [
            ['UND', 'Unidad', 'TRUE'], // ya existe -> se omite
            ['NU', 'Nueva Unidad', 'TRUE'],
        ];

        $clasificacionesHeaders = [
            'code', 'name', 'description',
            'has_sanitary_registration', 'has_concentration', 'has_risk_level',
            'has_pharma_fields', 'has_device_fields', 'has_lab_brand',
        ];
        $clasificacionesRows = [
            ['DM', 'Dispositivo Médico', '', 'TRUE', 'FALSE', 'TRUE', 'FALSE', 'TRUE', 'TRUE'], // ya existe -> se omite
            ['CLS-NUEVA', 'Clasificación Nueva', 'Clasificación creada por importación', 'TRUE', 'FALSE', 'FALSE', 'FALSE', 'FALSE', 'FALSE'],
        ];

        $file = $this->makeMultiSheetUpload([
            'Productos'          => ['headers' => $productosHeaders, 'rows' => $productosRows],
            'Categorías'         => ['headers' => $categoriasHeaders, 'rows' => $categoriasRows],
            'Unidades de medida' => ['headers' => $unidadesHeaders, 'rows' => $unidadesRows],
            'Clasificaciones'    => ['headers' => $clasificacionesHeaders, 'rows' => $clasificacionesRows],
        ], 'productos_con_catalogos.xlsx');

        $response = $this->withHeaders($this->auth())
            ->post('/api/v1/import/products', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.success', 1)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.related_catalogs.categories.total', 3)
            ->assertJsonPath('data.related_catalogs.categories.created', 1)
            ->assertJsonPath('data.related_catalogs.categories.skipped', 1)
            ->assertJsonPath('data.related_catalogs.categories.failed', 1)
            ->assertJsonPath('data.related_catalogs.units_of_measure.total', 2)
            ->assertJsonPath('data.related_catalogs.units_of_measure.created', 1)
            ->assertJsonPath('data.related_catalogs.units_of_measure.skipped', 1)
            ->assertJsonPath('data.related_catalogs.units_of_measure.failed', 0)
            ->assertJsonPath('data.related_catalogs.classifications.total', 2)
            ->assertJsonPath('data.related_catalogs.classifications.created', 1)
            ->assertJsonPath('data.related_catalogs.classifications.skipped', 1)
            ->assertJsonPath('data.related_catalogs.classifications.failed', 0);

        $newCategory = CategoryModel::where('code', 'CAT-NUEVA')->first();
        $this->assertNotNull($newCategory);
        $this->assertSame(
            CategoryModel::where('code', 'INS-MED')->first()->id,
            $newCategory->parent_id,
        );

        $newUnit = UnitOfMeasureModel::where('abbreviation', 'NU')->first();
        $this->assertNotNull($newUnit);
        $this->assertTrue((bool) $newUnit->is_base);

        $newClassification = ProductClassificationModel::where('code', 'CLS-NUEVA')->first();
        $this->assertNotNull($newClassification);
        $this->assertTrue($newClassification->has_sanitary_registration);

        $product = ProductModel::where('code', 'IMP-010')->first();
        $this->assertNotNull($product);
        $this->assertSame($newCategory->id, $product->category_id);
        $this->assertSame($newUnit->id, $product->base_unit_id);
        $this->assertSame($newClassification->id, $product->classification_id);

        $this->assertFalse(CategoryModel::where('code', 'CAT-INVALIDA')->exists());
    }
}
