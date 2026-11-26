<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Integration\Infrastructure\ExternalServices\MedsysAppointmentService;
use App\Modules\Integration\Infrastructure\ExternalServices\MedsysPatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Búsqueda de pacientes y citas de MedSys: la pueden usar `integraciones.ver`
 * y también quienes registran procedimientos o generan órdenes de servicio
 * (los pacientes solo existen en MedSys). Lo administrativo de MedSys sigue
 * exigiendo `integraciones.ver`.
 */
class MedsysPatientAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $patients = $this->mock(MedsysPatientService::class);
        $patients->shouldReceive('findByDocument', 'findByName')->andReturn(collect([
            (object) ['codigo' => 'P1', 'documento' => '100', 'nombre' => 'Paciente Prueba'],
        ]));
        $patients->shouldReceive('listProcedureTypes')->andReturn(collect());

        $appointments = $this->mock(MedsysAppointmentService::class);
        $appointments->shouldReceive('getPatientSummary', 'getActiveAppointments')->andReturn(collect());
    }

    /** Usuario con SOLO los permisos indicados (sin rol). */
    private function userWith(string ...$permissions): array
    {
        $user = UserModel::create([
            'name'      => 'Usuario de prueba',
            'email'     => uniqid('u', true).'@example.com',
            'password'  => bcrypt('Test1234!'),
            'is_active' => true,
        ]);
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);

        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function asRole(string $role): array
    {
        $user = UserModel::create([
            'name'      => "Usuario {$role}",
            'email'     => "{$role}@example.com",
            'password'  => bcrypt('Test1234!'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        Auth::forgetGuards();

        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    public function test_integraciones_ver_sigue_pudiendo_buscar(): void
    {
        $this->getJson('/api/v1/medsys/patients?search=1000', $this->userWith('integraciones.ver'))
            ->assertOk()
            ->assertJsonPath('data.patients.0.codigo', 'P1');
    }

    public function test_registrar_procedimientos_permite_buscar_pacientes_y_citas(): void
    {
        $headers = $this->userWith('registros_procedimientos.crear');

        $this->getJson('/api/v1/medsys/patients?search=1000', $headers)->assertOk();
        $this->getJson('/api/v1/medsys/patients/P1/appointments', $headers)->assertOk();
    }

    public function test_generar_ordenes_de_servicio_permite_buscar_pacientes_y_citas(): void
    {
        $headers = $this->userWith('ordenes_servicio.crear');

        $this->getJson('/api/v1/medsys/patients?search=1000', $headers)->assertOk();
        $this->getJson('/api/v1/medsys/patients/P1/appointments', $headers)->assertOk();
    }

    public function test_solo_ver_registros_u_ordenes_no_alcanza(): void
    {
        $headers = $this->userWith('registros_procedimientos.ver', 'ordenes_servicio.ver');

        $this->getJson('/api/v1/medsys/patients?search=1000', $headers)->assertForbidden();
        $this->getJson('/api/v1/medsys/patients/P1/appointments', $headers)->assertForbidden();
    }

    public function test_sin_permisos_responde_403_con_mensaje_en_espanol(): void
    {
        $this->getJson('/api/v1/medsys/patients?search=1000', $this->userWith('tablero.ver'))
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'No tienes ninguno de los permisos requeridos (integraciones.ver, registros_procedimientos.crear, ordenes_servicio.crear) para realizar esta acción.']);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/v1/medsys/patients?search=1000')->assertUnauthorized();
    }

    public function test_lo_administrativo_de_medsys_sigue_exigiendo_integraciones_ver(): void
    {
        $headers = $this->userWith('registros_procedimientos.crear', 'ordenes_servicio.crear');

        $this->getJson('/api/v1/medsys/procedure-types', $headers)->assertForbidden();
        $this->getJson('/api/v1/medsys/consumption-projection', $headers)->assertForbidden();
        $this->getJson('/api/v1/scheduling/appointments', $headers)->assertForbidden();
    }

    public function test_los_roles_clinicos_existentes_ya_pueden_buscar_pacientes(): void
    {
        foreach (['operador_almacen', 'jefe_almacen', 'personal_medico'] as $role) {
            $this->getJson('/api/v1/medsys/patients?search=1000', $this->asRole($role))
                ->assertOk();
        }
    }

    public function test_el_auditor_no_gana_acceso(): void
    {
        $this->getJson('/api/v1/medsys/patients?search=1000', $this->asRole('auditor'))->assertForbidden();
    }
}
