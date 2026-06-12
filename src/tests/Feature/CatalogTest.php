<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_crud_categoria_y_producto(): void
    {
        $cat = CategoryModel::first();

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/categories-tree')
            ->assertOk();

        $unit = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', [
                'category_id'  => $cat->id,
                'base_unit_id' => $unit->id,
                'product_type' => 'simple',
                'name'         => 'Producto Test',
                'code'         => 'PRD-TEST-001',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $productId = $response->json('data.id');

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.code', 'PRD-TEST-001');
    }

    public function test_presentaciones_y_conversion(): void
    {
        $aguja = ProductModel::where('code', 'AGU-21G')->first();
        $presentation = ProductPresentationModel::where('code', 'PQ-100')->first();

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/products/{$aguja->id}/presentations")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/presentations/convert-to-base', [
                'presentation_id' => $presentation->id,
                'quantity'        => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.quantity_base', 200);
    }

    public function test_kit_explosion(): void
    {
        $kit = ProductModel::where('code', 'KIT-CIR-BAS')->first();

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/products/{$kit->id}/kit-components")
            ->assertOk();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/products/{$kit->id}/kit-components/explode", [
                'quantity_kits' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_codigo_producto_duplicado_422(): void
    {
        $cat = CategoryModel::first();
        $unit = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $payload = [
            'category_id'  => $cat->id,
            'base_unit_id' => $unit->id,
            'name'         => 'Dup',
            'code'         => 'DUP-001',
        ];

        $this->withHeaders($this->auth())->postJson('/api/v1/products', $payload)->assertStatus(201);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/products', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
