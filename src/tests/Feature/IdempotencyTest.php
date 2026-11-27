<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Shared\Infrastructure\Persistence\Models\IdempotencyKeyModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cabecera opcional `Idempotency-Key` en la creación de movimientos y órdenes de servicio.
 */
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $this->admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $this->admin->createToken('test', $this->admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function headers(?string $key = null): array
    {
        Auth::forgetGuards();

        return array_filter([
            'Authorization'   => "Bearer {$this->token}",
            'Idempotency-Key' => $key,
        ]);
    }

    /** @return array<string, mixed> */
    private function entryPayload(string $lot = 'LOT-IDEM'): array
    {
        $warehouse = WarehouseModel::firstOrCreate(['code' => 'ALM-IDEM'], ['name' => 'Almacén Idem', 'is_active' => true]);
        $zone      = ZoneModel::firstOrCreate(
            ['code' => 'Z-IDEM'],
            ['warehouse_id' => $warehouse->id, 'name' => 'Zona Idem', 'type' => 'ambient', 'is_active' => true],
        );
        $location = LocationModel::firstOrCreate(
            ['code' => 'U-IDEM'],
            ['zone_id' => $zone->id, 'name' => 'Ubicación Idem', 'is_active' => true],
        );
        $generic = GenericProductModel::where('barcode', '000001')->firstOrFail();
        $variant = ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();

        return [
            'warehouse_id' => $warehouse->id,
            'items'        => [[
                'product_variant_id' => $variant->id,
                'location_id'        => $location->id,
                'lot_number'         => $lot,
                'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
                'quantity_base'      => 10,
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function orderPayload(string $document = '1122334455'): array
    {
        return [
            'patient_external_id' => "EXT-{$document}",
            'patient_document'    => $document,
            'patient_first_name'  => 'Laura',
            'patient_last_name'   => 'Gómez',
            'service_date'        => now()->toDateString(),
            'procedures'          => [[
                'medical_service_id' => MedicalServiceModel::where('type', 'procedure')->first()->id,
                'unit_price'         => 150000,
                'quantity'           => 1,
            ]],
        ];
    }

    public function test_sin_la_cabecera_cada_solicitud_crea_su_propio_movimiento(): void
    {
        $payload = $this->entryPayload();

        $this->withHeaders($this->headers())->postJson('/api/v1/movements/entry', $payload)->assertStatus(201);
        $this->withHeaders($this->headers())->postJson('/api/v1/movements/entry', $payload)->assertStatus(201);

        $this->assertSame(2, \DB::table('movement_documents')->count());
        $this->assertSame(0, IdempotencyKeyModel::count());
    }

    public function test_la_repeticion_con_la_misma_clave_devuelve_la_respuesta_original_sin_duplicar(): void
    {
        $payload = $this->entryPayload();

        $first = $this->withHeaders($this->headers('clave-mov-1'))
            ->postJson('/api/v1/movements/entry', $payload)
            ->assertStatus(201);

        $second = $this->withHeaders($this->headers('clave-mov-1'))
            ->postJson('/api/v1/movements/entry', $payload)
            ->assertStatus(201)
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertSame(1, \DB::table('movement_documents')->count());
        $this->assertSame(1, IdempotencyKeyModel::count());
    }

    public function test_la_misma_clave_con_otro_cuerpo_se_rechaza(): void
    {
        $this->withHeaders($this->headers('clave-mov-2'))
            ->postJson('/api/v1/movements/entry', $this->entryPayload('LOT-A'))
            ->assertStatus(201);

        $this->withHeaders($this->headers('clave-mov-2'))
            ->postJson('/api/v1/movements/entry', $this->entryPayload('LOT-B'))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(1, \DB::table('movement_documents')->count());
    }

    public function test_una_solicitud_fallida_no_consume_la_clave(): void
    {
        $invalid = ['warehouse_id' => 999999, 'items' => []];

        $this->withHeaders($this->headers('clave-mov-3'))
            ->postJson('/api/v1/movements/entry', $invalid)
            ->assertStatus(422);

        $this->assertSame(0, IdempotencyKeyModel::count());

        $this->withHeaders($this->headers('clave-mov-3'))
            ->postJson('/api/v1/movements/entry', $this->entryPayload())
            ->assertStatus(201);
    }

    public function test_una_solicitud_en_curso_con_la_misma_clave_responde_409(): void
    {
        $payload = $this->entryPayload();

        IdempotencyKeyModel::create([
            'user_id'      => $this->admin->id,
            'key'          => 'clave-mov-4',
            'path'         => 'api/v1/movements/entry',
            'request_hash' => hash('sha256', json_encode($payload)),
        ]);

        $this->withHeaders($this->headers('clave-mov-4'))
            ->postJson('/api/v1/movements/entry', $payload)
            ->assertStatus(409);

        $this->assertSame(0, \DB::table('movement_documents')->count());
    }

    public function test_la_clave_es_por_ruta_y_no_se_confunde_con_otra_operacion(): void
    {
        $payload = $this->entryPayload();

        $this->withHeaders($this->headers('clave-comun'))
            ->postJson('/api/v1/movements/entry', $payload)
            ->assertStatus(201);

        $this->withHeaders($this->headers('clave-comun'))
            ->postJson('/api/v1/movements/exit', ['warehouse_id' => $payload['warehouse_id'], 'items' => []])
            ->assertStatus(422)
            ->assertHeaderMissing('Idempotent-Replayed');
    }

    public function test_rechaza_claves_con_formato_invalido(): void
    {
        $this->withHeaders($this->headers('clave con espacios'))
            ->postJson('/api/v1/movements/entry', $this->entryPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->withHeaders($this->headers(str_repeat('a', 101)))
            ->postJson('/api/v1/movements/entry', $this->entryPayload())
            ->assertStatus(422);
    }

    public function test_las_ordenes_de_servicio_tambien_son_idempotentes(): void
    {
        Notification::fake();
        $payload = $this->orderPayload();

        $first = $this->withHeaders($this->headers('clave-orden-1'))
            ->postJson('/api/v1/service-orders', $payload)
            ->assertStatus(201);

        $second = $this->withHeaders($this->headers('clave-orden-1'))
            ->postJson('/api/v1/service-orders', $payload)
            ->assertStatus(201)
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.order_number'), $second->json('data.order_number'));
        $this->assertSame(1, \DB::table('patient_procedure_records')->count());
    }

    public function test_sin_la_cabecera_las_ordenes_siguen_funcionando_igual(): void
    {
        Notification::fake();

        $this->withHeaders($this->headers())->postJson('/api/v1/service-orders', $this->orderPayload('1001'))->assertStatus(201);
        $this->withHeaders($this->headers())->postJson('/api/v1/service-orders', $this->orderPayload('1001'))->assertStatus(201);

        $this->assertSame(2, \DB::table('patient_procedure_records')->count());
    }

    public function test_las_claves_vencidas_se_eliminan_con_model_prune(): void
    {
        $old = IdempotencyKeyModel::create(['user_id' => $this->admin->id, 'key' => 'vieja', 'path' => 'p', 'request_hash' => str_repeat('a', 64)]);
        $old->forceFill(['created_at' => now()->subHours(IdempotencyKeyModel::RETENTION_HOURS + 1)])->save();
        IdempotencyKeyModel::create(['user_id' => $this->admin->id, 'key' => 'nueva', 'path' => 'p', 'request_hash' => str_repeat('b', 64)]);

        $this->artisan('model:prune', ['--model' => [IdempotencyKeyModel::class]])->assertSuccessful();

        $this->assertSame(['nueva'], IdempotencyKeyModel::pluck('key')->all());
    }
}
