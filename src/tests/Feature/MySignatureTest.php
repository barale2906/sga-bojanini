<?php

namespace Tests\Feature;

use App\Modules\Auth\Application\Services\SignaturePinService;
use App\Modules\Auth\Infrastructure\Persistence\Models\RetiredSignaturePinModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserSignatureModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Cubre "Mi firma": registro, versionado y PIN de la firma del usuario
 * autenticado, y la regla de privacidad (la firma de un usuario jamás es
 * visible para otro, sin importar su rol).
 */
class MySignatureTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private const PASSWORD = 'Test1234!';

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $this->admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
    }

    /**
     * Cabeceras de autenticación para el usuario dado.
     *
     * Olvida los guards resueltos antes de cada cambio de usuario (el guard
     * de Sanctum cachea el usuario durante la vida del contenedor de test).
     */
    private function as(UserModel $user): array
    {
        Auth::forgetGuards();
        $token = $user->createToken('test', $user->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    private function createUser(string $email, string $role = 'operador_almacen'): UserModel
    {
        $user = UserModel::create([
            'name'      => "Usuario {$email}",
            'email'     => $email,
            'password'  => bcrypt(self::PASSWORD),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'current_password' => self::PASSWORD,
            'document_type'    => 'CC',
            'document_number'  => '1020304050',
            'signature'        => self::SIGNATURE,
        ], $overrides);
    }

    /** Registra la firma del usuario y devuelve el PIN generado. */
    private function register(UserModel $user, array $overrides = []): string
    {
        return $this->withHeaders($this->as($user))
            ->putJson('/api/v1/auth/me/signature', $this->payload($overrides))
            ->assertStatus(201)
            ->json('data.pin');
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/v1/auth/me/signature')->assertStatus(401);
        $this->putJson('/api/v1/auth/me/signature', $this->payload())->assertStatus(401);
        $this->postJson('/api/v1/auth/me/signature/pin', [])->assertStatus(401);
    }

    public function test_usuario_sin_firma_obtiene_null_y_catalogo_de_documentos(): void
    {
        $user = $this->createUser('sinfirma@test.com');

        $this->withHeaders($this->as($user))
            ->getJson('/api/v1/auth/me/signature')
            ->assertStatus(200)
            ->assertJsonPath('data.signature', null)
            ->assertJsonPath('data.document_types.0.value', 'CC');
    }

    public function test_primer_registro_genera_pin_unico_de_tres_digitos(): void
    {
        $user = $this->createUser('firma@test.com');

        $response = $this->withHeaders($this->as($user))
            ->putJson('/api/v1/auth/me/signature', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.signature.document_type', 'CC')
            ->assertJsonPath('data.signature.document_number', '1020304050')
            ->assertJsonPath('data.signature.has_pin', true);

        $pin = $response->json('data.pin');
        $this->assertMatchesRegularExpression('/^\d{3}$/', $pin);

        // Se guarda el HMAC, nunca el PIN en claro
        $row = UserSignatureModel::where('user_id', $user->id)->first();
        $this->assertNotSame($pin, $row->pin_lookup);
        $this->assertSame(app(SignaturePinService::class)->lookup($pin), $row->pin_lookup);
    }

    public function test_consultar_firma_nunca_devuelve_pin_ni_su_hmac(): void
    {
        $user = $this->createUser('consulta@test.com');
        $pin  = $this->register($user);

        $response = $this->withHeaders($this->as($user))
            ->getJson('/api/v1/auth/me/signature')
            ->assertStatus(200)
            ->assertJsonPath('data.signature.signature_data', self::SIGNATURE)
            ->assertJsonPath('data.signature.has_pin', true)
            ->assertJsonMissingPath('data.signature.pin')
            ->assertJsonMissingPath('data.signature.pin_lookup');

        $this->assertStringNotContainsString($pin, $response->getContent());
    }

    public function test_registrar_firma_exige_contrasena_actual_correcta(): void
    {
        $user = $this->createUser('pwd@test.com');

        $this->withHeaders($this->as($user))
            ->putJson('/api/v1/auth/me/signature', $this->payload(['current_password' => 'otra-clave']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertDatabaseCount('user_signatures', 0);
    }

    public function test_valida_tipo_documento_numero_y_formato_de_imagen(): void
    {
        $user = $this->createUser('valida@test.com');

        $this->withHeaders($this->as($user))
            ->putJson('/api/v1/auth/me/signature', $this->payload([
                'document_type'   => 'XX',
                'document_number' => '10.203.040',
                'signature'       => 'data:image/jpeg;base64,AAAA',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document_type', 'document_number', 'signature']);
    }

    public function test_actualizar_firma_crea_nueva_version_y_conserva_el_pin(): void
    {
        $user = $this->createUser('version@test.com');
        $pin  = $this->register($user);

        $this->withHeaders($this->as($user))
            ->putJson('/api/v1/auth/me/signature', $this->payload(['document_number' => '99887766']))
            ->assertStatus(200)
            ->assertJsonPath('data.pin', null)
            ->assertJsonPath('data.signature.document_number', '99887766')
            ->assertJsonPath('data.signature.has_pin', true);

        $this->assertSame(2, UserSignatureModel::where('user_id', $user->id)->count());
        $this->assertSame(1, UserSignatureModel::where('user_id', $user->id)->where('is_active', true)->count());

        // El PIN quedó solo en la versión activa
        $lookup = app(SignaturePinService::class)->lookup($pin);
        $active = UserSignatureModel::where('pin_lookup', $lookup)->first();
        $this->assertTrue($active->is_active);
        $this->assertSame('99887766', $active->document_number);
    }

    public function test_regenerar_pin_invalida_el_anterior(): void
    {
        $user   = $this->createUser('regen@test.com');
        $oldPin = $this->register($user);

        $newPin = $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/me/signature/pin', ['current_password' => self::PASSWORD])
            ->assertStatus(200)
            ->json('data.pin');

        $this->assertMatchesRegularExpression('/^\d{3}$/', $newPin);
        $this->assertNotSame($oldPin, $newPin);

        $pins = app(SignaturePinService::class);
        $this->assertDatabaseMissing('user_signatures', ['pin_lookup' => $pins->lookup($oldPin)]);
        $this->assertDatabaseHas('user_signatures', ['user_id' => $user->id, 'pin_lookup' => $pins->lookup($newPin)]);
    }

    public function test_regenerar_pin_exige_contrasena_y_firma_registrada(): void
    {
        $user = $this->createUser('regen2@test.com');

        $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/me/signature/pin', ['current_password' => self::PASSWORD])
            ->assertStatus(409);

        $this->register($user);

        $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/me/signature/pin', ['current_password' => 'mala'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_la_firma_de_un_usuario_no_es_visible_para_otro_aunque_sea_super_administrador(): void
    {
        $owner = $this->createUser('dueno@test.com');
        $this->register($owner, ['document_number' => '5551234']);

        // El super_administrador consulta "su" firma: obtiene la suya (null), nunca la del dueño
        $this->withHeaders($this->as($this->admin))
            ->getJson('/api/v1/auth/me/signature?user_id=' . $owner->id)
            ->assertStatus(200)
            ->assertJsonPath('data.signature', null);

        // Tampoco se filtra por la administración de usuarios
        $response = $this->withHeaders($this->as($this->admin))
            ->getJson("/api/v1/users/{$owner->id}")
            ->assertStatus(200);

        $this->assertStringNotContainsString('5551234', $response->getContent());
        $this->assertStringNotContainsString(self::SIGNATURE, $response->getContent());
    }

    public function test_un_usuario_no_puede_registrar_ni_regenerar_la_firma_de_otro(): void
    {
        $owner = $this->createUser('owner2@test.com');
        $this->register($owner, ['document_number' => '7777777']);

        $attacker = $this->createUser('otro@test.com');

        // Aunque envíe user_id, la firma se registra a su propio nombre
        $this->withHeaders($this->as($attacker))
            ->putJson('/api/v1/auth/me/signature', $this->payload(['user_id' => $owner->id, 'document_number' => '1111222']))
            ->assertStatus(201);

        $this->assertDatabaseHas('user_signatures', ['user_id' => $attacker->id, 'document_number' => '1111222', 'is_active' => true]);
        $this->assertDatabaseHas('user_signatures', ['user_id' => $owner->id, 'document_number' => '7777777', 'is_active' => true]);
        $this->assertSame(1, UserSignatureModel::where('user_id', $owner->id)->count());
    }

    public function test_pines_generados_no_son_triviales(): void
    {
        $pins = app(SignaturePinService::class);

        for ($i = 0; $i < 300; $i++) {
            $pin = $pins->generateUnique()['pin'];
            $this->assertNotSame(1, count(array_unique(str_split($pin))), "PIN trivial generado: {$pin}");
            $this->assertFalse(str_contains('0123456789', $pin) || str_contains('9876543210', $pin), "PIN secuencial generado: {$pin}");
        }
    }

    public function test_cuando_el_azar_falla_recorre_todos_los_pin_disponibles(): void
    {
        $pins = app(SignaturePinService::class);
        $user = $this->createUser('lleno@test.com');

        // Ocupa todos los PIN válidos menos uno
        $free  = null;
        $taken = 0;
        for ($n = 0; $n < 1000; $n++) {
            $pin = str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            if (count(array_unique(str_split($pin))) === 1 || str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
                continue;
            }
            if ($free === null) {
                $free = $pin;
                continue;
            }
            UserSignatureModel::create([
                'user_id' => $user->id, 'document_type' => 'CC', 'document_number' => 'X' . $n,
                'signature_data' => self::SIGNATURE, 'pin_lookup' => $pins->lookup($pin), 'is_active' => false,
            ]);
            $taken++;
        }

        $this->assertSame($free, $pins->generateUnique()['pin']);

        // Sin ninguno libre se informa con un error de dominio
        UserSignatureModel::create([
            'user_id' => $user->id, 'document_type' => 'CC', 'document_number' => 'LAST',
            'signature_data' => self::SIGNATURE, 'pin_lookup' => $pins->lookup($free), 'is_active' => false,
        ]);

        $this->expectException(\DomainException::class);
        $pins->generateUnique();
    }

    public function test_pin_regenerado_queda_en_cuarentena_180_dias(): void
    {
        $this->freezeSecond();
        $pins   = app(SignaturePinService::class);
        $user   = $this->createUser('cuarentena@test.com');
        $oldPin = $this->register($user);

        $this->withHeaders($this->as($user))
            ->postJson('/api/v1/auth/me/signature/pin', ['current_password' => self::PASSWORD])
            ->assertStatus(200);

        $retired = RetiredSignaturePinModel::where('pin_lookup', $pins->lookup($oldPin))->first();
        $this->assertNotNull($retired);
        $this->assertSame($user->id, $retired->user_id);
        $this->assertTrue($retired->available_at->equalTo(now()->addDays(180)));

        // Ya no lo usa ninguna firma, pero sigue sin poder asignarse
        $this->assertDatabaseMissing('user_signatures', ['pin_lookup' => $pins->lookup($oldPin)]);
        $this->assertTrue(app(\App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface::class)->pinLookupExists($pins->lookup($oldPin)));

        $this->travel(179)->days();
        $this->assertTrue(app(\App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface::class)->pinLookupExists($pins->lookup($oldPin)));

        $this->travel(2)->days();
        $this->assertFalse(app(\App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface::class)->pinLookupExists($pins->lookup($oldPin)));
    }

    public function test_actualizar_la_firma_no_envia_el_pin_a_cuarentena(): void
    {
        $user = $this->createUser('sincuarentena@test.com');
        $this->register($user);

        $this->withHeaders($this->as($user))
            ->putJson('/api/v1/auth/me/signature', $this->payload(['document_number' => '55443322']))
            ->assertStatus(200);

        $this->assertDatabaseCount('retired_signature_pins', 0);
    }

    public function test_el_generador_no_asigna_pines_en_cuarentena_hasta_que_vencen(): void
    {
        $this->freezeSecond();
        $pins = app(SignaturePinService::class);
        $user = $this->createUser('generador@test.com');

        // Ocupa todos los PIN válidos menos uno, que queda en cuarentena
        $quarantined = null;
        $rows = [];
        for ($n = 0; $n < 1000; $n++) {
            $pin = str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            if (count(array_unique(str_split($pin))) === 1 || str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
                continue;
            }
            if ($quarantined === null) {
                $quarantined = $pin;
                continue;
            }
            $rows[] = [
                'user_id' => $user->id, 'document_type' => 'CC', 'document_number' => 'X' . $n,
                'signature_data' => self::SIGNATURE, 'pin_lookup' => $pins->lookup($pin), 'is_active' => false,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        UserSignatureModel::insert($rows);
        RetiredSignaturePinModel::create([
            'pin_lookup'   => $pins->lookup($quarantined),
            'user_id'      => $user->id,
            'retired_at'   => now(),
            'available_at' => now()->addDays(SignaturePinService::QUARANTINE_DAYS),
        ]);

        try {
            $pins->generateUnique();
            $this->fail('No debió asignar un PIN en cuarentena.');
        } catch (\DomainException $e) {
            $this->assertSame('No hay PIN de firma disponibles. Contacte al administrador.', $e->getMessage());
        }

        $this->travel(SignaturePinService::QUARANTINE_DAYS + 1)->days();
        $this->assertSame($quarantined, $pins->generateUnique()['pin']);
    }
}
