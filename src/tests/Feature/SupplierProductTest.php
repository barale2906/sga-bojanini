<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private SupplierModel $supplier;
    private CategoryModel $category;
    private ProductVariantModel $agujaVariant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $this->supplier = SupplierModel::where('tax_id', '900123456-1')->first();
        $this->category = CategoryModel::where('code', 'INS-MED')->first();

        // Aguja (barcode 000001) pre-asignada al proveedor con datos pivot conocidos
        $agujaGeneric       = GenericProductModel::where('barcode', '000001')->first();
        $this->agujaVariant = ProductVariantModel::where('generic_product_id', $agujaGeneric->id)->first();
        $this->supplier->products()->attach($this->agujaVariant->id, [
            'lead_time_days' => 7,
            'unit_price'     => 150000,
            'is_preferred'   => true,
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function test_index_lista_variantes_del_proveedor(): void
    {
        $this->withHeaders($this->auth())
            ->getJson("/api/v1/suppliers/{$this->supplier->id}/variants")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'lab_brand', 'is_active', 'generic', 'pivot'],
                ],
            ]);
    }

    public function test_index_incluye_datos_del_pivot(): void
    {
        $response = $this->withHeaders($this->auth())
            ->getJson("/api/v1/suppliers/{$this->supplier->id}/variants")
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
            ->getJson('/api/v1/suppliers/99999/variants')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_index_sin_autenticacion_retorna_401(): void
    {
        $this->getJson("/api/v1/suppliers/{$this->supplier->id}/variants")
            ->assertUnauthorized();
    }

    // ─── attach (individual) ───────────────────────────────────────────────────

    public function test_attach_asigna_variante_con_datos_pivot(): void
    {
        $gasaGeneric  = GenericProductModel::where('barcode', '000002')->first();
        $gasaVariant  = ProductVariantModel::where('generic_product_id', $gasaGeneric->id)->first();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$gasaVariant->id}", [
                'supplier_sku'   => 'GAS-PROV-001',
                'lead_time_days' => 3,
                'unit_price'     => 25000,
                'is_preferred'   => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.generic.barcode', '000002')
            ->assertJsonPath('data.pivot.supplier_sku', 'GAS-PROV-001')
            ->assertJsonPath('data.pivot.lead_time_days', 3)
            ->assertJsonPath('data.pivot.unit_price', 25000);

        $this->assertDatabaseHas('product_variant_supplier', [
            'supplier_id'        => $this->supplier->id,
            'product_variant_id' => $gasaVariant->id,
            'supplier_sku'       => 'GAS-PROV-001',
            'lead_time_days'     => 3,
        ]);
    }

    public function test_attach_sin_body_usa_valores_por_defecto(): void
    {
        $gasaGeneric = GenericProductModel::where('barcode', '000002')->first();
        $gasaVariant = ProductVariantModel::where('generic_product_id', $gasaGeneric->id)->first();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$gasaVariant->id}")
            ->assertStatus(201);

        $pivot = $response->json('data.pivot');
        $this->assertEquals(0, $pivot['lead_time_days']);
        $this->assertEquals(0.0, $pivot['unit_price']);
        $this->assertFalse($pivot['is_preferred']);
        $this->assertNull($pivot['supplier_sku']);
    }

    public function test_attach_duplicado_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$this->agujaVariant->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_attach_proveedor_inexistente_retorna_404(): void
    {
        $gasaGeneric = GenericProductModel::where('barcode', '000002')->first();
        $gasaVariant = ProductVariantModel::where('generic_product_id', $gasaGeneric->id)->first();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/99999/variants/{$gasaVariant->id}")
            ->assertNotFound();
    }

    public function test_attach_variante_inexistente_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/99999")
            ->assertNotFound();
    }

    public function test_attach_valida_lead_time_negativo(): void
    {
        $gasaGeneric = GenericProductModel::where('barcode', '000002')->first();
        $gasaVariant = ProductVariantModel::where('generic_product_id', $gasaGeneric->id)->first();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$gasaVariant->id}", [
                'lead_time_days' => -1,
            ])
            ->assertStatus(422);
    }

    // ─── attachByCategory ─────────────────────────────────────────────────────

    public function test_attach_by_category_asigna_variantes_activas(): void
    {
        // Agujas (000001) ya está asignada; Gasa (000002) y Kit (000003) no
        $totalActive = ProductVariantModel::where('is_active', true)
            ->whereHas('genericProduct', fn ($q) => $q->where('category_id', $this->category->id)->where('is_active', true))
            ->count();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [
                'category_id'    => $this->category->id,
                'lead_time_days' => 5,
                'unit_price'     => 1000,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        // Aguja ya estaba asignada (skipped=1), el resto se asigna
        $this->assertEquals(1, $response->json('data.skipped'));
        $this->assertEquals($totalActive - 1, $response->json('data.assigned'));
        $this->assertEquals('Insumos Médicos', $response->json('data.category'));

        // Verifica que Gasa tiene los valores pivot correctos
        $gasaGeneric = GenericProductModel::where('barcode', '000002')->first();
        $gasaVariant = ProductVariantModel::where('generic_product_id', $gasaGeneric->id)->first();
        $this->assertDatabaseHas('product_variant_supplier', [
            'supplier_id'        => $this->supplier->id,
            'product_variant_id' => $gasaVariant->id,
            'lead_time_days'     => 5,
        ]);
    }

    public function test_attach_by_category_todas_ya_asignadas(): void
    {
        // Asignar todas primero
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk();

        // Segunda llamada: todas ya están asignadas
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned', 0);
    }

    public function test_attach_by_category_sin_category_id_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [])
            ->assertStatus(422);
    }

    public function test_attach_by_category_categoria_inexistente_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [
                'category_id' => 99999,
            ])
            ->assertStatus(422);
    }

    public function test_attach_by_category_proveedor_inexistente_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/suppliers/99999/variants/by-category', [
                'category_id' => $this->category->id,
            ])
            ->assertNotFound();
    }

    // ─── update ────────────────────────────────────────────────────────────────

    public function test_update_modifica_datos_del_pivot(): void
    {
        $this->withHeaders($this->auth())
            ->putJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$this->agujaVariant->id}", [
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

        $this->assertDatabaseHas('product_variant_supplier', [
            'supplier_id'        => $this->supplier->id,
            'product_variant_id' => $this->agujaVariant->id,
            'unit_price'         => 180000,
            'lead_time_days'     => 10,
        ]);
    }

    public function test_update_variante_no_asignada_retorna_404(): void
    {
        $gasaGeneric = GenericProductModel::where('barcode', '000002')->first();
        $gasaVariant = ProductVariantModel::where('generic_product_id', $gasaGeneric->id)->first();

        $this->withHeaders($this->auth())
            ->putJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$gasaVariant->id}", [
                'unit_price' => 5000,
            ])
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    // ─── detach (individual) ───────────────────────────────────────────────────

    public function test_detach_elimina_asignacion_del_proveedor(): void
    {
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$this->agujaVariant->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('product_variant_supplier', [
            'supplier_id'        => $this->supplier->id,
            'product_variant_id' => $this->agujaVariant->id,
        ]);
    }

    public function test_detach_variante_no_asignada_retorna_404(): void
    {
        $gasaGeneric = GenericProductModel::where('barcode', '000002')->first();
        $gasaVariant = ProductVariantModel::where('generic_product_id', $gasaGeneric->id)->first();

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$gasaVariant->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_detach_proveedor_inexistente_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/99999/variants/{$this->agujaVariant->id}")
            ->assertNotFound();
    }

    // ─── detachByCategory ─────────────────────────────────────────────────────

    public function test_detach_by_category_elimina_variantes_de_la_categoria(): void
    {
        // Primero asignar todas las variantes de la categoría
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk();

        $response = $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category', 'Insumos Médicos');

        $removed = $response->json('data.removed');
        $this->assertGreaterThan(0, $removed);

        // Verificar que no quedan registros en product_variant_supplier para esa categoría
        $variantIds = ProductVariantModel::whereHas(
            'genericProduct', fn ($q) => $q->where('category_id', $this->category->id)
        )->pluck('id');

        foreach ($variantIds as $vid) {
            $this->assertDatabaseMissing('product_variant_supplier', [
                'supplier_id'        => $this->supplier->id,
                'product_variant_id' => $vid,
            ]);
        }
    }

    public function test_detach_by_category_sin_variantes_asignadas_retorna_removed_cero(): void
    {
        // Eliminar la única variante asignada (aguja)
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/variants/{$this->agujaVariant->id}")
            ->assertOk();

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [
                'category_id' => $this->category->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.removed', 0);
    }

    public function test_detach_by_category_sin_category_id_retorna_422(): void
    {
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}/variants/by-category", [])
            ->assertStatus(422);
    }

    public function test_detach_by_category_proveedor_inexistente_retorna_404(): void
    {
        $this->withHeaders($this->auth())
            ->deleteJson('/api/v1/suppliers/99999/variants/by-category', [
                'category_id' => $this->category->id,
            ])
            ->assertNotFound();
    }
}
