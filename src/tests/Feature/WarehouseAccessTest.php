<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Cubre la asignación de almacenes a usuarios y su enforcement:
 * un usuario solo puede ver/operar los almacenes que tenga asignados,
 * salvo que tenga el rol super_administrador (acceso total).
 */
class WarehouseAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

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
            'name'      => "Usuario {$role}",
            'email'     => $email,
            'password'  => bcrypt('Test1234!'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    // ── Auto-asignación al crear ────────────────────────────────────────────

    public function test_almacen_creado_via_api_se_asigna_automaticamente_al_super_administrador(): void
    {
        $response = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/warehouses', [
                'name' => 'Almacén Auto',
                'code' => 'ALM-AUTO',
            ]);
        $response->assertStatus(201);
        $warehouseId = $response->json('data.id');

        $this->assertDatabaseHas('user_warehouse', [
            'user_id'      => $this->admin->id,
            'warehouse_id' => $warehouseId,
        ]);
    }

    // ── Asignación de almacenes a usuarios ──────────────────────────────────

    public function test_asignar_y_listar_almacenes_de_un_usuario(): void
    {
        $warehouse1 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/warehouses', ['name' => 'Almacén 1', 'code' => 'ALM-A1'])
            ->json('data.id');
        $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/warehouses', ['name' => 'Almacén 2', 'code' => 'ALM-A2'])
            ->json('data.id');

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe1@sga.bojanini.com');

        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$manager->id}/warehouses", ['warehouse_ids' => [$warehouse1]])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.warehouses');

        $this->withHeaders($this->asAdmin())
            ->getJson("/api/v1/users/{$manager->id}/warehouses")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // El almacén también es visible para el super_administrador (auto-asignado
        // al crearse), por lo que puede haber más de un usuario en la lista.
        $usersResponse = $this->withHeaders($this->asAdmin())
            ->getJson("/api/v1/warehouses/{$warehouse1}/users");
        $usersResponse->assertStatus(200);
        $userIds = collect($usersResponse->json('data'))->pluck('id');
        $this->assertTrue($userIds->contains($manager->id));
    }

    public function test_asignar_array_vacio_de_almacenes_quita_todos_los_asignados(): void
    {
        $warehouse1 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/warehouses', ['name' => 'Almacén Empty', 'code' => 'ALM-EMPTY'])
            ->json('data.id');

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe-empty@sga.bojanini.com');

        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$manager->id}/warehouses", ['warehouse_ids' => [$warehouse1]])
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.warehouses');

        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$manager->id}/warehouses", ['warehouse_ids' => []])
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.warehouses');
    }

    public function test_usuario_sin_permiso_de_asignacion_no_puede_asignar_almacenes(): void
    {
        $warehouse1 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/warehouses', ['name' => 'Almacén X', 'code' => 'ALM-X'])
            ->json('data.id');

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe2@sga.bojanini.com');

        // jefe_almacen no tiene el permiso 'almacenes.asignar'.
        $this->withHeaders($this->asUser($manager))
            ->putJson("/api/v1/users/{$manager->id}/warehouses", ['warehouse_ids' => [$warehouse1]])
            ->assertStatus(403);
    }

    // ── Enforcement sobre el módulo Warehouse ───────────────────────────────

    public function test_usuario_restringido_solo_ve_y_accede_a_sus_almacenes_asignados(): void
    {
        $warehouse1 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/warehouses', ['name' => 'Almacén Asignado', 'code' => 'ALM-AS1'])
            ->json('data.id');
        $warehouse2 = $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/warehouses', ['name' => 'Almacén No Asignado', 'code' => 'ALM-AS2'])
            ->json('data.id');

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe3@sga.bojanini.com');
        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$manager->id}/warehouses", ['warehouse_ids' => [$warehouse1]]);

        // El listado solo debe incluir el almacén asignado.
        $list = $this->withHeaders($this->asUser($manager))->getJson('/api/v1/warehouses');
        $list->assertStatus(200);
        $codes = collect($list->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('ALM-AS1'));
        $this->assertFalse($codes->contains('ALM-AS2'));

        // Acceso directo al almacén asignado: permitido.
        $this->withHeaders($this->asUser($manager))
            ->getJson("/api/v1/warehouses/{$warehouse1}")
            ->assertStatus(200);

        // Acceso directo al almacén NO asignado: prohibido.
        $this->withHeaders($this->asUser($manager))
            ->getJson("/api/v1/warehouses/{$warehouse2}")
            ->assertStatus(403);

        $this->withHeaders($this->asUser($manager))
            ->putJson("/api/v1/warehouses/{$warehouse2}", ['name' => 'Hackeado', 'code' => 'ALM-AS2'])
            ->assertStatus(403);
    }

    public function test_super_administrador_conserva_acceso_total_aunque_no_tenga_asignacion_explicita(): void
    {
        // Creado directamente en BD (sin pasar por el UseCase), por lo que no
        // queda ninguna fila en user_warehouse para nadie.
        $warehouse = WarehouseModel::create(['name' => 'Almacén Directo', 'code' => 'ALM-DIRECT', 'is_active' => true]);

        $this->withHeaders($this->asAdmin())
            ->getJson("/api/v1/warehouses/{$warehouse->id}")
            ->assertStatus(200);
    }

    // ── Enforcement sobre Inventory ──────────────────────────────────────────

    public function test_usuario_no_puede_registrar_entrada_en_almacen_no_asignado(): void
    {
        $allowed = $this->createWarehouseSetup('-ALLOWED');
        $forbidden = $this->createWarehouseSetup('-FORBIDDEN');
        $product = ProductModel::where('code', 'AGU-21G')->first();

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe4@sga.bojanini.com');
        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$manager->id}/warehouses", ['warehouse_ids' => [$allowed['warehouse']->id]]);

        $this->withHeaders($this->asUser($manager))
            ->postJson('/api/v1/movements/entry', [
                'product_id'      => $product->id,
                'warehouse_id'    => $forbidden['warehouse']->id,
                'location_id'     => $forbidden['location']->id,
                'lot_number'      => 'LOT-FORBIDDEN',
                'expiration_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity_base'   => 10,
            ])
            ->assertStatus(403);

        $this->withHeaders($this->asUser($manager))
            ->postJson('/api/v1/movements/entry', [
                'product_id'      => $product->id,
                'warehouse_id'    => $allowed['warehouse']->id,
                'location_id'     => $allowed['location']->id,
                'lot_number'      => 'LOT-ALLOWED',
                'expiration_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity_base'   => 10,
            ])
            ->assertStatus(201);
    }

    public function test_listado_de_movimientos_se_filtra_por_almacenes_asignados(): void
    {
        $allowed = $this->createWarehouseSetup('-MOVLIST-A');
        $forbidden = $this->createWarehouseSetup('-MOVLIST-B');
        $product = ProductModel::where('code', 'AGU-21G')->first();

        // Movimiento en el almacén permitido.
        $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/movements/entry', [
                'product_id'      => $product->id,
                'warehouse_id'    => $allowed['warehouse']->id,
                'location_id'     => $allowed['location']->id,
                'lot_number'      => 'LOT-MOVLIST-A',
                'expiration_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity_base'   => 10,
            ])
            ->assertStatus(201);

        // Movimiento en el almacén no permitido.
        $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/movements/entry', [
                'product_id'      => $product->id,
                'warehouse_id'    => $forbidden['warehouse']->id,
                'location_id'     => $forbidden['location']->id,
                'lot_number'      => 'LOT-MOVLIST-B',
                'expiration_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity_base'   => 10,
            ])
            ->assertStatus(201);

        $manager = $this->createUserWithRole('jefe_almacen', 'jefe5@sga.bojanini.com');
        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$manager->id}/warehouses", ['warehouse_ids' => [$allowed['warehouse']->id]]);

        $response = $this->withHeaders($this->asUser($manager))->getJson('/api/v1/movements');
        $response->assertStatus(200);

        $warehouseIds = collect($response->json('data'))->pluck('warehouse_id');
        $this->assertTrue($warehouseIds->contains($allowed['warehouse']->id));
        $this->assertFalse($warehouseIds->contains($forbidden['warehouse']->id));
    }

    /**
     * @return array{warehouse: WarehouseModel, zone: ZoneModel, location: LocationModel}
     */
    private function createWarehouseSetup(string $suffix = ''): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => "Almacén Access Test{$suffix}",
            'code'      => "ALM-ACC{$suffix}",
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => "Zona Access{$suffix}",
            'code'         => "Z-ACC{$suffix}",
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $location = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => "Ubicación Access{$suffix}",
            'code'      => "U-ACC{$suffix}",
            'is_active' => true,
        ]);

        return compact('warehouse', 'zone', 'location');
    }
}
