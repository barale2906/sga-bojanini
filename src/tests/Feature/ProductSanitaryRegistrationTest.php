<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use App\Modules\Catalog\Domain\Enums\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSanitaryRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private ProductVariantModel $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ProductClassificationSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $cat  = CategoryModel::firstOrCreate(['code' => 'INS-MED'], ['name' => 'Insumos Médicos', 'is_active' => true]);
        $unit = UnitOfMeasureModel::firstOrCreate(['abbreviation' => 'UND'], ['name' => 'Unidad', 'is_base' => true, 'is_active' => true]);
        $dm   = ProductClassificationModel::where('code', 'DM')->first();

        $maxBarcode = (int) GenericProductModel::max('barcode');
        $barcode    = str_pad((string) ($maxBarcode + 1), 6, '0', STR_PAD_LEFT);

        $generic = GenericProductModel::create([
            'category_id'       => $cat->id,
            'classification_id' => $dm?->id,
            'base_unit_id'      => $unit->id,
            'product_type'      => ProductType::Simple->value,
            'name'              => 'Catéter venoso',
            'barcode'           => $barcode,
            'is_active'         => true,
        ]);

        $this->variant = ProductVariantModel::create([
            'generic_product_id' => $generic->id,
            'risk_level'         => 'Clase IIA',
            'lab_brand'          => 'Medline',
            'is_active'          => true,
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_crud_registro_sanitario(): void
    {
        // Crear
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations", [
                'registration_number' => 'INVIMA-2024-001',
                'expiry_date'         => '2028-12-31',
                'is_active'           => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.registration_number', 'INVIMA-2024-001')
            ->assertJsonPath('data.expiry_date', '2028-12-31')
            ->assertJsonPath('data.is_expired', false);

        $registrationId = $response->json('data.id');

        // Listar
        $this->withHeaders($this->auth())
            ->getJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Actualizar
        $this->withHeaders($this->auth())
            ->putJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations/{$registrationId}", [
                'registration_number' => 'INVIMA-2024-001',
                'expiry_date'         => '2030-06-30',
                'is_active'           => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.expiry_date', '2030-06-30');

        // Eliminar
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations/{$registrationId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_multiples_registros_por_producto(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations", [
                'registration_number' => 'INVIMA-2024-001',
                'expiry_date'         => '2028-12-31',
            ])->assertStatus(201);

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations", [
                'registration_number' => 'INVIMA-2026-002',
                'expiry_date'         => '2030-06-30',
            ])->assertStatus(201);

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_registro_duplicado_rechazado(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations", [
                'registration_number' => 'INVIMA-2024-001',
                'expiry_date'         => '2028-12-31',
            ])->assertStatus(201);

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations", [
                'registration_number' => 'INVIMA-2024-001',
                'expiry_date'         => '2029-01-01',
            ])->assertStatus(409); // DomainException → 409 Conflict
    }

    public function test_filtrar_solo_vigentes(): void
    {
        // Registro vencido
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations", [
                'registration_number' => 'INVIMA-OLD-001',
                'expiry_date'         => '2020-01-01',
                'is_active'           => true,
            ])->assertStatus(201);

        // Registro vigente
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations", [
                'registration_number' => 'INVIMA-2024-001',
                'expiry_date'         => '2028-12-31',
                'is_active'           => true,
            ])->assertStatus(201);

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations?only_active=true")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_producto_inexistente_devuelve_error(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/variants/9999/sanitary-registrations', [
                'registration_number' => 'INVIMA-2024-001',
                'expiry_date'         => '2028-12-31',
            ])->assertStatus(409); // DomainException → 409 Conflict
    }

    public function test_fecha_invalida_rechazada(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/variants/{$this->variant->id}/sanitary-registrations", [
                'registration_number' => 'INVIMA-2024-001',
                'expiry_date'         => 'no-es-una-fecha',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['expiry_date']);
    }
}
