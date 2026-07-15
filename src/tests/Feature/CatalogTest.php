<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
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
            ->postJson('/api/v1/generic-products', [
                'category_id'  => $cat->id,
                'base_unit_id' => $unit->id,
                'product_type' => 'simple',
                'name'         => 'Producto Test',
            ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $productId = $response->json('data.id');

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/generic-products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Producto Test');

        $this->assertTrue(
            ProductVariantModel::where('generic_product_id', $productId)->where('is_active', true)->exists(),
            'Al crear un producto simple debe generarse automáticamente una variante activa.'
        );
    }

    public function test_presentaciones_y_conversion(): void
    {
        $aguja = GenericProductModel::where('barcode', '000001')->firstOrFail();
        $presentation = ProductPresentationModel::where('code', 'PQ-100')->first();

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/generic-products/{$aguja->id}/presentations")
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
        $kit = GenericProductModel::where('barcode', '000003')->firstOrFail();

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/generic-products/{$kit->id}/kit-components")
            ->assertOk();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/generic-products/{$kit->id}/kit-components/explode", [
                'quantity_kits' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_crear_generico_sin_campos_requeridos_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/generic-products', [
                'name' => 'Sin categoría ni unidad',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
