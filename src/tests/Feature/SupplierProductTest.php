<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private SupplierModel $supplier;
    private CategoryModel $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);

        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $this->supplier = SupplierModel::where('tax_id', '900123456-1')->first();
        $this->category = CategoryModel::where('code', 'INS-MED')->first();
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function test_index_lista_productos_del_proveedor(): void
    {
        $this->withHeaders($this->auth())
            ->getJson("/api/v1/suppliers/{$this->supplier->id}/products")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'code', 'is_active', 'category', 'pivot'],
                ],
            ]);
    }

    public function test_index_incluye_datos_del_pivot(): void
    {
        $response = $this->withHeaders($this->auth())
            ->getJson("/api/v1/suppliers/{$this->supplier->id}/products")
            ->assertOk();

        $pivot = $response->json('data.0.pivot');
        $this->assertArrayHasKey('lead_time_days', $pivot);
        $this->assertArrayHasKey('unit_price', $pivot);
        $this->assertArrayHasKey('is_preferred', $pivot);
        $this->assertEquals(7, $pivot['lead_time_days']);
        $this->assertEquals(150000, $pivot['unit_price']);
        $this->assertTrue($pivot['is_preferred']);
    }

    public function test_index_proveedor_inexistente_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/suppliers/99999/products')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_index_sin_autenticacion_retorna_401(): void
    {
        $this->getJson("/api/v1/suppliers/{$this->supplier->id}/products")
            ->assertUnauthorized();
    }

    // ─── attach (individual) ───────────────────────────────────────────────────

    public function test_attach_asigna_producto_con_datos_pivot(): void
    {
        $gasa = ProductModel::where('code', 'GAS-10X10')->first();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/{$gasa->id}", [
                'supplier_sku'   => 'GAS-PROV-001',
                'lead_time_days' => 3,
                'unit_price'     => 25000,
                'is_preferred'   => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'GAS-10X10')
            ->assertJsonPath('data.pivot.supplier_sku', 'GAS-PROV-001')
            ->assertJsonPath('data.pivot.lead_time_days', 3)
            ->assertJsonPath('data.pivot.unit_price', 25000);

        $this->assertDatabaseHas('product_supplier', [
            'supplier_id'   => $this->supplier->id,
            'product_id'    => $gasa->id,
            'supplier_sku'  => 'GAS-PROV-001',
            'lead_time_days'=> 3,
        ]);
    }

    public function test_attach_sin_body_usa_valores_por_defecto(): void
    {
        $gasa = ProductModel::where('code', 'GAS-10X10')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/{$gasa->id}")
            ->assertStatus(201);

        $pivot = $response->json('data.pivot');
        $this->assertEquals(0, $pivot['lead_time_days']);
        $this->assertEquals(0.0, $pivot['unit_price']);
        $this->assertFalse($pivot['is_preferred']);
        $this->assertNull($pivot['supplier_sku']);
    }

    public function test_attach_duplicado_retorna_422(): void
    {
        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/{$aguja->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_attach_proveedor_inexistente_retorna_404(): void
    {
        $gasa = ProductModel::where('code', 'GAS-10X10')->first();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/99999/products/{$gasa->id}")
            ->assertNotFound();
    }

    public function test_attach_producto_inexistente_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/99999")
            ->assertNotFound();
    }

    public function test_attach_valida_lead_time_negativo(): void
    {
        $gasa = ProductModel::where('code', 'GAS-10X10')->first();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/{$gasa->id}", [
                'lead_time_days' => -1,
            ])
            ->assertStatus(422);
    }

    // ─── attachByCategory ─────────────────────────────────────────────────────

    public function test_attach_by_category_asigna_productos_activos(): void
    {
        // AGU-21G ya está asignado; GAS-10X10 y KIT-CIR-BAS no
        $totalActive = ProductModel::where('category_id', $this->category->id)
            ->where('is_active', true)
            ->count();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [
                'category_id'    => $this->category->id,
                'lead_time_days' => 5,
                'unit_price'     => 1000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // AGU-21G estaba ya asignado (skipped=1), el resto se asignan
        $this->assertEquals(1, $response->json('data.skipped'));
        $this->assertEquals($totalActive - 1, $response->json('data.assigned'));
        $this->assertEquals('Insumos Médicos', $response->json('data.category'));

        // Verifica que los nuevos productos tienen los valores pivot correctos
        $gasa = ProductModel::where('code', 'GAS-10X10')->first();
        $this->assertDatabaseHas('product_supplier', [
            'supplier_id'    => $this->supplier->id,
            'product_id'     => $gasa->id,
            'lead_time_days' => 5,
        ]);
    }

    public function test_attach_by_category_todos_ya_asignados(): void
    {
        // Asignar todos primero
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk();

        // Segunda llamada: todos ya están asignados
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned', 0);
    }

    public function test_attach_by_category_sin_category_id_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [])
            ->assertStatus(422);
    }

    public function test_attach_by_category_categoria_inexistente_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [
                'category_id' => 99999,
            ])
            ->assertStatus(422);
    }

    public function test_attach_by_category_proveedor_inexistente_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/suppliers/99999/products/by-category', [
                'category_id' => $this->category->id,
            ])
            ->assertNotFound();
    }

    // ─── update ────────────────────────────────────────────────────────────────

    public function test_update_modifica_datos_del_pivot(): void
    {
        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        $this->withHeaders($this->auth())
            ->putJson("/api/v1/suppliers/{$this->supplier->id}/products/{$aguja->id}", [
                'unit_price'     => 180000,
                'lead_time_days' => 10,
                'is_preferred'   => false,
                'supplier_sku'   => 'SKU-NUEVO',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pivot.unit_price', 180000)
            ->assertJsonPath('data.pivot.lead_time_days', 10)
            ->assertJsonPath('data.pivot.is_preferred', false)
            ->assertJsonPath('data.pivot.supplier_sku', 'SKU-NUEVO');

        $this->assertDatabaseHas('product_supplier', [
            'supplier_id'  => $this->supplier->id,
            'product_id'   => $aguja->id,
            'unit_price'   => 180000,
            'lead_time_days' => 10,
        ]);
    }

    public function test_update_producto_no_asignado_retorna_404(): void
    {
        $gasa = ProductModel::where('code', 'GAS-10X10')->first();

        $this->withHeaders($this->auth())
            ->putJson("/api/v1/suppliers/{$this->supplier->id}/products/{$gasa->id}", [
                'unit_price' => 5000,
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ─── detach (individual) ───────────────────────────────────────────────────

    public function test_detach_elimina_asignacion_del_proveedor(): void
    {
        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/products/{$aguja->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('product_supplier', [
            'supplier_id' => $this->supplier->id,
            'product_id'  => $aguja->id,
        ]);
    }

    public function test_detach_producto_no_asignado_retorna_404(): void
    {
        $gasa = ProductModel::where('code', 'GAS-10X10')->first();

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/products/{$gasa->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_detach_proveedor_inexistente_retorna_404(): void
    {
        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/99999/products/{$aguja->id}")
            ->assertNotFound();
    }

    // ─── detachByCategory ─────────────────────────────────────────────────────

    public function test_detach_by_category_elimina_productos_de_la_categoria(): void
    {
        // Primero asignar todos los productos de la categoría
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk();

        $response = $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category', 'Insumos Médicos');

        $removed = $response->json('data.removed');
        $this->assertGreaterThan(0, $removed);

        // Verificar que no quedan registros en product_supplier para esa categoría
        $categoryProductIds = ProductModel::where('category_id', $this->category->id)->pluck('id');
        foreach ($categoryProductIds as $pid) {
            $this->assertDatabaseMissing('product_supplier', [
                'supplier_id' => $this->supplier->id,
                'product_id'  => $pid,
            ]);
        }
    }

    public function test_detach_by_category_sin_productos_asignados_retorna_removed_cero(): void
    {
        // La categoría no tiene productos asignados (solo AGU-21G está asignado)
        // Eliminar AGU-21G primero
        $aguja = ProductModel::where('code', 'AGU-21G')->first();
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/products/{$aguja->id}")
            ->assertOk();

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.removed', 0);
    }

    public function test_detach_by_category_sin_category_id_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/products/by-category", [])
            ->assertStatus(422);
    }

    public function test_detach_by_category_proveedor_inexistente_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->deleteJson('/api/v1/suppliers/99999/products/by-category', [
                'category_id' => $this->category->id,
            ])
            ->assertNotFound();
    }
}
