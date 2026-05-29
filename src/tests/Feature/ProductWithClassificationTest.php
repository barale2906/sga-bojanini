<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWithClassificationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $admin       = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function basePayload(array $overrides = []): array
    {
        $cat  = \App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel::first();
        $unit = \App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        return array_merge([
            'category_id'  => $cat->id,
            'base_unit_id' => $unit->id,
            'name'         => 'Producto Test',
            'code'         => 'PRD-CLS-001',
        ], $overrides);
    }

    public function test_crear_medicamento_con_todos_los_campos(): void
    {
        $med = ProductClassificationModel::where('code', 'MED')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', $this->basePayload([
                'code'                    => 'MED-001',
                'name'                    => 'Acetaminofén 500mg',
                'classification_id'       => $med->id,
                'concentration'           => '500mg',
                'lab_brand'               => 'Genfar',
                'pharmaceutical_form'     => 'Tableta',
                'commercial_presentation' => 'Caja x 100 Tab',
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.classification_id', $med->id)
            ->assertJsonPath('data.concentration', '500mg')
            ->assertJsonPath('data.lab_brand', 'Genfar')
            ->assertJsonPath('data.pharmaceutical_form', 'Tableta')
            ->assertJsonPath('data.commercial_presentation', 'Caja x 100 Tab');
    }

    public function test_crear_dispositivo_medico_con_campos_de_dispositivo(): void
    {
        $dm = ProductClassificationModel::where('code', 'DM')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', $this->basePayload([
                'code'             => 'DM-001',
                'name'             => 'Monitor de signos vitales',
                'classification_id'=> $dm->id,
                'risk_level'       => 'Clase IIA',
                'lab_brand'        => 'Philips Healthcare',
                'serie_reference'  => 'SRP-1000X',
                'useful_life'      => '10 años',
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.risk_level', 'Clase IIA')
            ->assertJsonPath('data.lab_brand', 'Philips Healthcare')
            ->assertJsonPath('data.serie_reference', 'SRP-1000X')
            ->assertJsonPath('data.useful_life', '10 años');
    }

    public function test_laboratorio_marca_requerido_por_clasificacion(): void
    {
        $med = ProductClassificationModel::where('code', 'MED')->first();

        // Sin lab_brand → use case rechaza la creación (DomainException → 409 Conflict)
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', $this->basePayload([
                'code'             => 'MED-ERR-001',
                'classification_id'=> $med->id,
                'concentration'    => '500mg',
            ]))
            ->assertStatus(409);
    }

    public function test_crear_producto_sin_clasificacion_permitido(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', $this->basePayload([
                'code' => 'SIN-CLAS-001',
                'name' => 'Producto sin clasificar',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.classification_id', null);
    }

    public function test_show_incluye_clasificacion_y_registros_sanitarios(): void
    {
        $dm  = ProductClassificationModel::where('code', 'DM')->first();
        $cat = \App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel::first();
        $unit = \App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $created = $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', [
                'category_id'      => $cat->id,
                'base_unit_id'     => $unit->id,
                'classification_id'=> $dm->id,
                'name'             => 'Guante estéril',
                'code'             => 'GUA-EST-001',
                'risk_level'       => 'Clase I',
                'lab_brand'        => 'Cardinal Health',
            ])->json('data.id');

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/products/{$created}")
            ->assertOk()
            ->assertJsonPath('data.classification.code', 'DM')
            ->assertJsonPath('data.sanitary_registrations', []);
    }

    public function test_clasificacion_inexistente_rechazada(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', $this->basePayload([
                'code'             => 'ERR-001',
                'classification_id'=> 9999,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['classification_id']);
    }

    public function test_campos_farmaceuticos_nullables_en_dispositivo(): void
    {
        $dm = ProductClassificationModel::where('code', 'DM')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', $this->basePayload([
                'code'             => 'DM-NULL-001',
                'name'             => 'Dispositivo sin forma farmacéutica',
                'classification_id'=> $dm->id,
                'risk_level'       => 'Clase I',
                'lab_brand'        => 'Test Brand',
            ]))
            ->assertStatus(201);

        $this->assertNull($response->json('data.pharmaceutical_form'));
        $this->assertNull($response->json('data.commercial_presentation'));
    }
}
