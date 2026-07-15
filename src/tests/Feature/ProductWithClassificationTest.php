<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de creación de GenericProduct + ProductVariant con datos de clasificación.
 *
 * POST /api/v1/generic-products            → crea el concepto clínico (nombre, concentración, etc.)
 * POST /api/v1/generic-products/{id}/variants → crea la instancia de marca (lab_brand, risk_level, etc.)
 */
class ProductWithClassificationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private int $categoryId;
    private int $unitId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $this->categoryId = CategoryModel::first()->id;
        $this->unitId     = UnitOfMeasureModel::where('abbreviation', 'UND')->first()->id;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function createGeneric(array $overrides = []): array
    {
        return array_merge([
            'category_id'  => $this->categoryId,
            'base_unit_id' => $this->unitId,
            'name'         => 'Producto Test',
        ], $overrides);
    }

    // ─── Generic product creation ──────────────────────────────────────────────

    public function test_crear_medicamento_generico_con_campos_clinicos(): void
    {
        $med = ProductClassificationModel::where('code', 'MED')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', $this->createGeneric([
                'name'                => 'Acetaminofén 500mg',
                'classification_id'   => $med->id,
                'concentration'       => '500mg',
                'pharmaceutical_form' => 'Tableta',
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.classification_id', $med->id)
            ->assertJsonPath('data.concentration', '500mg')
            ->assertJsonPath('data.pharmaceutical_form', 'Tableta')
            ->assertJsonPath('data.name', 'Acetaminofén 500mg');
    }

    public function test_crear_variante_de_medicamento_con_lab_brand(): void
    {
        $med = ProductClassificationModel::where('code', 'MED')->first();

        $genericId = $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', $this->createGeneric([
                'name'                => 'Acetaminofén 500mg',
                'classification_id'   => $med->id,
                'concentration'       => '500mg',
                'pharmaceutical_form' => 'Tableta',
            ]))->json('data.id');

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/generic-products/{$genericId}/variants", [
                'lab_brand'               => 'Genfar',
                'commercial_presentation' => 'Caja x 100 Tab',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lab_brand', 'Genfar')
            ->assertJsonPath('data.commercial_presentation', 'Caja x 100 Tab');
    }

    public function test_crear_dispositivo_medico_variante_con_campos_de_dispositivo(): void
    {
        $dm = ProductClassificationModel::where('code', 'DM')->first();

        $genericId = $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', $this->createGeneric([
                'name'              => 'Monitor de signos vitales',
                'classification_id' => $dm->id,
            ]))->json('data.id');

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/generic-products/{$genericId}/variants", [
                'lab_brand'       => 'Philips Healthcare',
                'risk_level'      => 'Clase IIA',
                'serie_reference' => 'SRP-1000X',
                'useful_life'     => '10 años',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.risk_level', 'Clase IIA')
            ->assertJsonPath('data.lab_brand', 'Philips Healthcare')
            ->assertJsonPath('data.serie_reference', 'SRP-1000X')
            ->assertJsonPath('data.useful_life', '10 años');
    }

    public function test_laboratorio_marca_requerido_por_clasificacion(): void
    {
        $med = ProductClassificationModel::where('code', 'MED')->first();

        // El genérico se crea sin problema
        $genericId = $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', $this->createGeneric([
                'name'             => 'Paracetamol 500mg',
                'classification_id'=> $med->id,
                'concentration'    => '500mg',
            ]))->assertStatus(201)->json('data.id');

        // Crear la variante sin lab_brand cuando la clasificación lo requiere → 422 (validación en form request)
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/generic-products/{$genericId}/variants", [])
            ->assertStatus(422);
    }

    public function test_crear_producto_sin_clasificacion_permitido(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', $this->createGeneric([
                'name' => 'Producto sin clasificar',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.classification_id', null);
    }

    public function test_show_incluye_clasificacion_y_registros_sanitarios_anidados(): void
    {
        $dm = ProductClassificationModel::where('code', 'DM')->first();

        $genericId = $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', $this->createGeneric([
                'name'              => 'Guante estéril',
                'classification_id' => $dm->id,
            ]))->json('data.id');

        // Crear variante
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/generic-products/{$genericId}/variants", [
                'lab_brand' => 'Cardinal Health',
                'risk_level' => 'Clase I',
            ])->assertStatus(201);

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/generic-products/{$genericId}")
            ->assertOk()
            ->assertJsonPath('data.classification.code', 'DM')
            ->assertJsonStructure([
                'data' => ['variants' => [['id', 'lab_brand', 'sanitary_registrations']]],
            ]);
    }

    public function test_clasificacion_inexistente_rechazada(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', $this->createGeneric([
                'name'             => 'Producto Error',
                'classification_id'=> 9999,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['classification_id']);
    }

    public function test_campos_variante_nullables(): void
    {
        $dm = ProductClassificationModel::where('code', 'DM')->first();

        $genericId = $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', $this->createGeneric([
                'name'              => 'Dispositivo sin forma farmacéutica',
                'classification_id' => $dm->id,
            ]))->json('data.id');

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/generic-products/{$genericId}/variants", [
                'lab_brand'  => 'Test Brand',
                'risk_level' => 'Clase I',
            ])
            ->assertStatus(201);

        $this->assertNull($response->json('data.commercial_presentation'));
        $this->assertNull($response->json('data.serie_reference'));
        $this->assertNull($response->json('data.useful_life'));
    }
}
