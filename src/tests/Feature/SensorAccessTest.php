<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Cubre la asignación de sensores (equipos de monitoreo) a usuarios y su
 * enforcement: un usuario solo puede ver/operar los sensores que tenga
 * asignados, salvo que tenga el rol super_administrador (acceso total).
 */
class SensorAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->adminToken = $this->admin->createToken('test', $this->admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    /**
     * Cabeceras de autenticación para el token dado.
     *
     * Olvida los guards resueltos antes de cada cambio de usuario: en los
     * tests de Laravel el guard de Sanctum cachea el usuario autenticado
     * durante la vida del contenedor, por lo que al alternar tokens de
     * usuarios distintos dentro del mismo test hay que forzar su
     * re-resolución (esto no ocurre en producción, donde cada request HTTP
     * arranca un contenedor nuevo).
     */
    private function headersFor(string $token): array
    {
        Auth::forgetGuards();

        return ['Authorization' => "Bearer {$token}"];
    }

    private function asAdmin(): array
    {
        return $this->headersFor($this->adminToken);
    }

    private function asUser(UserModel $user): array
    {
        return $this->headersFor($user->createToken('test', $user->getAllPermissions()->pluck('name')->toArray())->plainTextToken);
    }

    /** Crea un usuario con un rol dado. */
    private function createUserWithRole(string $role, string $email): UserModel
    {
        $user = UserModel::create([
            'name' => "Usuario {$role}",
            'email' => $email,
            'password' => bcrypt('Test1234!'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** Crea un almacén y una zona (requeridos para registrar un sensor). */
    private function createZone(string $suffix = ''): ZoneModel
    {
        $warehouse = WarehouseModel::create([
            'name' => "Almacén Sensor Access{$suffix}",
            'code' => "ALM-SENS{$suffix}",
            'is_active' => true,
        ]);

        return ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name' => "Zona Sensor Access{$suffix}",
            'code' => "Z-SENS{$suffix}",
            'type' => 'cold',
            'is_active' => true,
        ]);
    }

    // ── Auto-asignación al crear ────────────────────────────────────────────

    public function test_sensor_creado_via_api_se_asigna_automaticamente_al_super_administrador(): void
    {
        $zone = $this->createZone('-AUTO');

        $response = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/sensors', [
                'zone_id' => $zone->id,
                'code' => 'SENS-AUTO',
                'name' => 'Sensor Auto',
                'type' => 'temperature',
                'unit' => 'C',
            ]);
        $response->assertStatus(201);
        $sensorId = $response->json('data.id');

        $this->assertDatabaseHas('user_sensor', [
            'user_id' => $this->admin->id,
            'sensor_id' => $sensorId,
        ]);
    }

    // ── Asignación de sensores a usuarios ───────────────────────────────────

    public function test_asignar_y_listar_sensores_de_un_usuario(): void
    {
        $zone = $this->createZone('-LIST');

        $sensor1 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/sensors', ['zone_id' => $zone->id, 'code' => 'SENS-L1', 'name' => 'Sensor 1', 'type' => 'temperature', 'unit' => 'C'])
            ->json('data.id');
        $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/sensors', ['zone_id' => $zone->id, 'code' => 'SENS-L2', 'name' => 'Sensor 2', 'type' => 'humidity', 'unit' => '%'])
            ->json('data.id');

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe-sens1@sga.bojanini.com');

        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$manager->id}/sensors", ['sensor_ids' => [$sensor1]])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.sensors');

        $this->withHeaders($this->asAdmin())
            ->getJson("/api/v1/users/{$manager->id}/sensors")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // El sensor también es visible para el super_administrador (auto-asignado
        // al crearse), por lo que puede haber más de un usuario en la lista.
        $usersResponse = $this->withHeaders($this->asAdmin())
            ->getJson("/api/v1/sensors/{$sensor1}/users");
        $usersResponse->assertStatus(200);
        $userIds = collect($usersResponse->json('data'))->pluck('id');
        $this->assertTrue($userIds->contains($manager->id));
    }

    public function test_usuario_sin_permiso_de_asignacion_no_puede_asignar_sensores(): void
    {
        $zone = $this->createZone('-NOPERM');

        $sensor1 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/sensors', ['zone_id' => $zone->id, 'code' => 'SENS-NP', 'name' => 'Sensor X', 'type' => 'temperature', 'unit' => 'C'])
            ->json('data.id');

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe-sens2@sga.bojanini.com');

        // jefe_almacen no tiene el permiso 'sensores.asignar'.
        $this->withHeaders($this->asUser($manager))
            ->putJson("/api/v1/users/{$manager->id}/sensors", ['sensor_ids' => [$sensor1]])
            ->assertStatus(403);
    }

    // ── Enforcement sobre el módulo Monitoring ──────────────────────────────

    public function test_usuario_restringido_solo_ve_y_accede_a_sus_sensores_asignados(): void
    {
        $zone = $this->createZone('-ENF');

        $sensor1 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/sensors', ['zone_id' => $zone->id, 'code' => 'SENS-ENF1', 'name' => 'Sensor Asignado', 'type' => 'temperature', 'unit' => 'C'])
            ->json('data.id');
        $sensor2 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/sensors', ['zone_id' => $zone->id, 'code' => 'SENS-ENF2', 'name' => 'Sensor No Asignado', 'type' => 'temperature', 'unit' => 'C'])
            ->json('data.id');

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe-sens3@sga.bojanini.com');
        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$manager->id}/sensors", ['sensor_ids' => [$sensor1]]);

        // El listado solo debe incluir el sensor asignado.
        $list = $this->withHeaders($this->asUser($manager))->getJson('/api/v1/sensors');
        $list->assertStatus(200);
        $codes = collect($list->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('SENS-ENF1'));
        $this->assertFalse($codes->contains('SENS-ENF2'));

        // Acceso directo al sensor asignado: permitido.
        $this->withHeaders($this->asUser($manager))
            ->getJson("/api/v1/sensors/{$sensor1}")
            ->assertStatus(200);

        // Acceso directo al sensor NO asignado: prohibido.
        $this->withHeaders($this->asUser($manager))
            ->getJson("/api/v1/sensors/{$sensor2}")
            ->assertStatus(403);

        $this->withHeaders($this->asUser($manager))
            ->putJson("/api/v1/sensors/{$sensor2}", [
                'zone_id' => $zone->id,
                'code' => 'SENS-ENF2',
                'name' => 'Hackeado',
                'type' => 'temperature',
                'unit' => 'C',
            ])
            ->assertStatus(403);

        // Registrar una lectura en un sensor no asignado también se prohíbe.
        $this->withHeaders($this->asUser($manager))
            ->postJson("/api/v1/sensors/{$sensor2}/readings", [
                'value' => 5.0,
                'reading_source' => 'manual',
                'recorded_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(403);
    }

    public function test_super_administrador_conserva_acceso_total_aunque_no_tenga_asignacion_explicita(): void
    {
        // Creado directamente en BD (sin pasar por el UseCase), por lo que no
        // queda ninguna fila en user_sensor para nadie.
        $zone = $this->createZone('-DIRECT');
        $sensor = SensorModel::create([
            'zone_id' => $zone->id,
            'code' => 'SENS-DIRECT',
            'name' => 'Sensor Directo',
            'type' => 'temperature',
            'unit' => 'C',
            'is_active' => true,
        ]);

        $this->withHeaders($this->asAdmin())
            ->getJson("/api/v1/sensors/{$sensor->id}")
            ->assertStatus(200);
    }
}
