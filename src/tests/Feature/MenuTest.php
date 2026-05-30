<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    // ─── Autenticación ────────────────────────────────────────────────────────

    public function test_sin_autenticacion_retorna_401(): void
    {
        $this->getJson('/api/v1/auth/menu')
            ->assertUnauthorized();
    }

    // ─── Estructura de la respuesta ───────────────────────────────────────────

    public function test_respuesta_tiene_estructura_correcta(): void
    {
        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $token = $this->bearerTokenFor($admin);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->assertOk()
            ->assertJsonPath('success', true);

        $firstItem = $response->json('data.0');
        $this->assertArrayHasKey('key',        $firstItem);
        $this->assertArrayHasKey('label',      $firstItem);
        $this->assertArrayHasKey('icon',       $firstItem);
        $this->assertArrayHasKey('route',      $firstItem);
        $this->assertArrayHasKey('permission', $firstItem);
        $this->assertArrayHasKey('actions',    $firstItem);
        $this->assertArrayHasKey('children',   $firstItem);
    }

    // ─── Super Administrador — ve todo ────────────────────────────────────────

    public function test_super_administrador_ve_once_secciones(): void
    {
        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $token = $this->bearerTokenFor($admin);

        $data = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->assertOk()
            ->json('data');

        $this->assertCount(11, $data);

        $keys = array_column($data, 'key');
        $this->assertContains('dashboard',   $keys);
        $this->assertContains('cost-center', $keys);
        $this->assertContains('admin',       $keys);
    }

    public function test_super_administrador_ve_centros_costo_con_acciones_completas(): void
    {
        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->first();
        $token = $this->bearerTokenFor($admin);

        $data    = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->json('data');

        $section  = $this->findSectionByKey($data, 'cost-center');
        $this->assertNotNull($section);
        $this->assertCount(2, $section['children']);

        $costCentersItem = $this->findSectionByKey($section['children'], 'cost-centers');
        $this->assertTrue($costCentersItem['actions']['create']);
        $this->assertTrue($costCentersItem['actions']['edit']);
        $this->assertTrue($costCentersItem['actions']['delete']);

        $servicesItem = $this->findSectionByKey($section['children'], 'medical-services');
        $this->assertTrue($servicesItem['actions']['create']);
        $this->assertTrue($servicesItem['actions']['edit']);
        $this->assertTrue($servicesItem['actions']['delete']);
    }

    // ─── Personal Médico ──────────────────────────────────────────────────────

    public function test_personal_medico_ve_seccion_centros_costo(): void
    {
        $user  = $this->authenticateAsRole('personal_medico');
        $token = $this->bearerTokenFor($user);

        $data = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->assertOk()
            ->json('data');

        $keys = array_column($data, 'key');
        $this->assertContains('cost-center', $keys);
    }

    public function test_personal_medico_no_puede_crear_ni_eliminar_centros(): void
    {
        $user  = $this->authenticateAsRole('personal_medico');
        $token = $this->bearerTokenFor($user);

        $data    = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->json('data');

        $section = $this->findSectionByKey($data, 'cost-center');
        $item    = $this->findSectionByKey($section['children'], 'cost-centers');

        $this->assertFalse($item['actions']['create']);
        $this->assertFalse($item['actions']['edit']);
        $this->assertFalse($item['actions']['delete']);
    }

    public function test_personal_medico_no_ve_seccion_administracion(): void
    {
        $user  = $this->authenticateAsRole('personal_medico');
        $token = $this->bearerTokenFor($user);

        $data = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->json('data');

        $keys = array_column($data, 'key');
        $this->assertNotContains('admin', $keys);
    }

    public function test_personal_medico_tiene_accion_exit_en_movimientos(): void
    {
        $user  = $this->authenticateAsRole('personal_medico');
        $token = $this->bearerTokenFor($user);

        $data      = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->json('data');

        $inventory = $this->findSectionByKey($data, 'inventory');
        $movements = $this->findSectionByKey($inventory['children'], 'movements');

        $this->assertTrue($movements['actions']['exit']);
        $this->assertFalse($movements['actions']['entry']);
    }

    // ─── Auditor — no ve centros de costo ─────────────────────────────────────

    public function test_auditor_no_ve_seccion_centros_costo(): void
    {
        $user  = $this->authenticateAsRole('auditor');
        $token = $this->bearerTokenFor($user);

        $data = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->json('data');

        $keys = array_column($data, 'key');
        $this->assertNotContains('cost-center', $keys);
    }

    public function test_auditor_puede_exportar_reportes(): void
    {
        $user  = $this->authenticateAsRole('auditor');
        $token = $this->bearerTokenFor($user);

        $data    = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->json('data');

        $reports = $this->findSectionByKey($data, 'reports');
        $this->assertNotNull($reports);
        $this->assertTrue($reports['actions']['export']);
    }

    // ─── Operador de Almacén — permisos parciales en centros ──────────────────

    public function test_operador_almacen_ve_centros_costo_sin_permisos_crud(): void
    {
        $user  = $this->authenticateAsRole('operador_almacen');
        $token = $this->bearerTokenFor($user);

        $data    = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->json('data');

        $section = $this->findSectionByKey($data, 'cost-center');
        $this->assertNotNull($section);

        $item = $this->findSectionByKey($section['children'], 'cost-centers');
        $this->assertFalse($item['actions']['create']);
        $this->assertFalse($item['actions']['edit']);
        $this->assertFalse($item['actions']['delete']);
    }

    // ─── Jefe de Almacén — puede crear y editar centros ──────────────────────

    public function test_jefe_almacen_puede_crear_y_editar_centros_pero_no_eliminar(): void
    {
        $user  = $this->authenticateAsRole('jefe_almacen');
        $token = $this->bearerTokenFor($user);

        $data    = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/menu')
            ->json('data');

        $section = $this->findSectionByKey($data, 'cost-center');
        $item    = $this->findSectionByKey($section['children'], 'cost-centers');

        $this->assertTrue($item['actions']['create']);
        $this->assertTrue($item['actions']['edit']);
        $this->assertFalse($item['actions']['delete']);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function findSectionByKey(array $items, string $key): ?array
    {
        foreach ($items as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }
}
