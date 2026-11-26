<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserDeviceModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Shared\Infrastructure\Notifications\PurchaseOrderPendingApprovalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;
use Tests\TestCase;

/**
 * Registro de dispositivos móviles (token push FCM) y su uso como destino
 * de las notificaciones. Cada usuario solo opera sobre sus propios tokens.
 */
class UserDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'fcm-token-de-prueba-1234567890:APA91bH';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
    }

    private function as(UserModel $user): array
    {
        Auth::forgetGuards();
        $token = $user->createToken('test', $user->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function createUser(string $email): UserModel
    {
        $user = UserModel::create([
            'name'      => "Usuario {$email}",
            'email'     => $email,
            'password'  => bcrypt('Test1234!'),
            'is_active' => true,
        ]);
        $user->assignRole('operador_almacen');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'token'       => self::TOKEN,
            'platform'    => 'android',
            'device_name' => 'SGA Android · a1b2c3',
        ], $overrides);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->postJson('/api/v1/auth/devices', $this->payload())->assertStatus(401);
        $this->deleteJson('/api/v1/auth/devices/'.self::TOKEN)->assertStatus(401);
    }

    public function test_registra_el_dispositivo_del_usuario(): void
    {
        $user = $this->createUser('uno@test.com');

        $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/devices', $this->payload())
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('user_devices', [
            'user_id'     => $user->id,
            'token'       => self::TOKEN,
            'platform'    => 'android',
            'device_name' => 'SGA Android · a1b2c3',
        ]);
    }

    public function test_registrar_dos_veces_el_mismo_token_no_duplica(): void
    {
        $user = $this->createUser('uno@test.com');

        $this->withHeaders($this->as($user))->postJson('/api/v1/auth/devices', $this->payload())->assertStatus(200);
        $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/devices', $this->payload(['device_name' => 'Otro nombre']))
            ->assertStatus(200);

        $this->assertSame(1, UserDeviceModel::count());
        $this->assertDatabaseHas('user_devices', ['token' => self::TOKEN, 'device_name' => 'Otro nombre']);
    }

    public function test_telefono_compartido_reasigna_el_token_al_nuevo_usuario(): void
    {
        $anterior = $this->createUser('anterior@test.com');
        $nuevo    = $this->createUser('nuevo@test.com');

        $this->withHeaders($this->as($anterior))->postJson('/api/v1/auth/devices', $this->payload())->assertStatus(200);
        $this->withHeaders($this->as($nuevo))->postJson('/api/v1/auth/devices', $this->payload())->assertStatus(200);

        $this->assertSame(1, UserDeviceModel::count());
        $this->assertSame([], $anterior->routeNotificationForFcm());
        $this->assertSame([self::TOKEN], $nuevo->routeNotificationForFcm());
    }

    public function test_valida_los_campos(): void
    {
        $user = $this->createUser('uno@test.com');

        $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/devices', ['platform' => 'windows'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'platform']);

        $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/devices', $this->payload(['token' => str_repeat('a', 256)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_desvincula_el_token_propio(): void
    {
        $user = $this->createUser('uno@test.com');
        $this->withHeaders($this->as($user))->postJson('/api/v1/auth/devices', $this->payload())->assertStatus(200);

        $this->withHeaders($this->as($user))
            ->deleteJson('/api/v1/auth/devices/'.self::TOKEN)
            ->assertStatus(200);

        $this->assertDatabaseMissing('user_devices', ['token' => self::TOKEN]);
    }

    public function test_no_puede_desvincular_el_token_de_otro_usuario(): void
    {
        $dueno = $this->createUser('dueno@test.com');
        $otro  = $this->createUser('otro@test.com');
        $this->withHeaders($this->as($dueno))->postJson('/api/v1/auth/devices', $this->payload())->assertStatus(200);

        $this->withHeaders($this->as($otro))
            ->deleteJson('/api/v1/auth/devices/'.self::TOKEN)
            ->assertStatus(404);

        $this->assertDatabaseHas('user_devices', ['user_id' => $dueno->id, 'token' => self::TOKEN]);
    }

    public function test_ruta_de_notificacion_fcm_devuelve_todos_los_tokens_del_usuario(): void
    {
        $user = $this->createUser('uno@test.com');
        $this->withHeaders($this->as($user))->postJson('/api/v1/auth/devices', $this->payload())->assertStatus(200);
        $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/devices', $this->payload(['token' => 'segundo-token']))
            ->assertStatus(200);

        $this->assertEqualsCanonicalizing(
            [self::TOKEN, 'segundo-token'],
            $user->routeNotificationFor('fcm', new PurchaseOrderPendingApprovalNotification('OC-1', 100.0)),
        );
    }

    public function test_usuario_sin_dispositivos_no_tiene_destinos_fcm(): void
    {
        $this->assertSame([], $this->createUser('sin@test.com')->routeNotificationForFcm());
    }

    public function test_elimina_el_token_que_fcm_reporta_desconocido(): void
    {
        $user = $this->createUser('uno@test.com');
        UserDeviceModel::create(['user_id' => $user->id, 'token' => self::TOKEN, 'platform' => 'android']);
        UserDeviceModel::create(['user_id' => $user->id, 'token' => 'vigente', 'platform' => 'android']);

        $report = SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, self::TOKEN), new NotFound('Requested entity was not found.'));
        $this->dispatchFailure($user, $report);

        $this->assertDatabaseMissing('user_devices', ['token' => self::TOKEN]);
        $this->assertDatabaseHas('user_devices', ['token' => 'vigente']);
    }

    public function test_no_elimina_el_token_ante_un_error_que_no_es_del_token(): void
    {
        $user = $this->createUser('uno@test.com');
        UserDeviceModel::create(['user_id' => $user->id, 'token' => self::TOKEN, 'platform' => 'android']);

        $report = SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, self::TOKEN), new InvalidMessage('Mensaje mal formado'));
        $this->dispatchFailure($user, $report);

        $this->assertDatabaseHas('user_devices', ['token' => self::TOKEN]);
    }

    public function test_ignora_fallos_de_otros_canales(): void
    {
        $user = $this->createUser('uno@test.com');
        UserDeviceModel::create(['user_id' => $user->id, 'token' => self::TOKEN, 'platform' => 'android']);

        $report = SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, self::TOKEN), new NotFound('x'));
        Event::dispatch(new NotificationFailed($user, new PurchaseOrderPendingApprovalNotification('OC-1', 100.0), 'mail', ['report' => $report]));

        $this->assertDatabaseHas('user_devices', ['token' => self::TOKEN]);
    }

    private function dispatchFailure(UserModel $user, SendReport $report): void
    {
        Event::dispatch(new NotificationFailed(
            $user,
            new PurchaseOrderPendingApprovalNotification('OC-1', 100.0),
            FcmChannel::class,
            ['report' => $report],
        ));
    }
}
