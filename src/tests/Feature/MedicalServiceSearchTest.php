<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ProcedurePriceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de integración para GET /api/v1/medical-services/search
 *
 * El endpoint devuelve procedimientos activos cuyo nombre o código coincida con
 * el término, incluyendo la tarifa vigente de cada uno.
 */
class MedicalServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin       = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ─── Validación ───────────────────────────────────────────────────────────

    public function test_requiere_parametro_q(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_rechaza_termino_de_un_caracter(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q=a')
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_sin_permiso_retorna_403(): void
    {
        $user = UserModel::create([
            'name'      => 'Sin Permisos',
            'email'     => 'noperms_search@sga.test',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $token = $user->createToken('test', [])->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/medical-services/search?q=cirug')
            ->assertForbidden();
    }

    // ─── Búsqueda por nombre ──────────────────────────────────────────────────

    public function test_busca_procedimientos_por_nombre(): void
    {
        $procedure = MedicalServiceModel::where('type', 'procedure')->first();

        $term = mb_substr($procedure->name, 0, 4);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q='.urlencode($term));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'parent_id', 'current_price'],
                ],
            ]);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($procedure->id, $ids);
    }

    // ─── Búsqueda por código ──────────────────────────────────────────────────

    public function test_busca_procedimientos_por_codigo(): void
    {
        $procedure = MedicalServiceModel::where('type', 'procedure')->first();

        $term = mb_substr($procedure->code, 0, 3);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q='.urlencode($term));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($procedure->id, $ids);
    }

    // ─── Precio incluido ──────────────────────────────────────────────────────

    public function test_incluye_tarifa_vigente_del_procedimiento(): void
    {
        $procedure = MedicalServiceModel::where('type', 'procedure')->first();

        ProcedurePriceModel::create([
            'medical_service_id' => $procedure->id,
            'unit_price'         => 75000,
            'effective_from'     => now()->toDateString(),
            'is_active'          => true,
        ]);

        $term = mb_substr($procedure->name, 0, 4);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q='.urlencode($term));

        $response->assertOk();

        $match = collect($response->json('data'))->firstWhere('id', $procedure->id);
        $this->assertNotNull($match, 'El procedimiento debe aparecer en los resultados');
        $this->assertNotNull($match['current_price']);
        $this->assertEquals(75000, $match['current_price']['unit_price']);
        $this->assertArrayHasKey('effective_from', $match['current_price']);
        $this->assertArrayHasKey('effective_to', $match['current_price']);
    }

    public function test_current_price_es_null_cuando_no_hay_tarifa_activa(): void
    {
        $service = MedicalServiceModel::where('type', 'service')->first();

        $procedure = MedicalServiceModel::create([
            'parent_id' => $service->id,
            'type'      => 'procedure',
            'code'      => 'TST-SIN-PRECIO',
            'name'      => 'Procedimiento Sin Tarifa',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q=Sin+Tarifa');

        $response->assertOk();

        $match = collect($response->json('data'))->firstWhere('id', $procedure->id);
        $this->assertNotNull($match);
        $this->assertNull($match['current_price']);
    }

    public function test_no_devuelve_tarifa_expirada(): void
    {
        $service = MedicalServiceModel::where('type', 'service')->first();

        $procedure = MedicalServiceModel::create([
            'parent_id' => $service->id,
            'type'      => 'procedure',
            'code'      => 'TST-EXPIRADA',
            'name'      => 'Procedimiento Tarifa Expirada',
            'is_active' => true,
        ]);

        ProcedurePriceModel::create([
            'medical_service_id' => $procedure->id,
            'unit_price'         => 30000,
            'effective_from'     => now()->subDays(30)->toDateString(),
            'effective_to'       => now()->subDay()->toDateString(),
            'is_active'          => true,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q=Tarifa+Expirada');

        $response->assertOk();

        $match = collect($response->json('data'))->firstWhere('id', $procedure->id);
        $this->assertNotNull($match);
        $this->assertNull($match['current_price']);
    }

    // ─── Filtros ──────────────────────────────────────────────────────────────

    public function test_no_devuelve_servicios_tipo_service(): void
    {
        $service = MedicalServiceModel::where('type', 'service')->first();

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q='.urlencode(mb_substr($service->name, 0, 5)));

        $response->assertOk();

        $types = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($service->id, $types);
    }

    public function test_no_devuelve_procedimientos_inactivos(): void
    {
        $service = MedicalServiceModel::where('type', 'service')->first();

        $procedure = MedicalServiceModel::create([
            'parent_id' => $service->id,
            'type'      => 'procedure',
            'code'      => 'TST-INACTIVO',
            'name'      => 'Procedimiento Inactivo Único',
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q=Inactivo+Único');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($procedure->id, $ids);
    }

    public function test_sin_coincidencias_devuelve_lista_vacia(): void
    {
        $response = $this->withHeaders($this->auth())
            ->getJson('/api/v1/medical-services/search?q=xqqzzyvvnomatch');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }
}
