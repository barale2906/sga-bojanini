<?php

namespace Tests\Feature;

use App\Modules\Auth\Application\DTOs\SaveSignatureData;
use App\Modules\Auth\Application\Services\RegisteredSignatureResolver;
use App\Modules\Auth\Application\Services\SignaturePinService;
use App\Modules\Auth\Application\UseCases\SaveMySignatureUseCase;
use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserSignatureModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Confirmación de comprobantes con firmas registradas:
 * quien entrega usa su firma por sesión y quien recibe la aplica con su PIN
 * (o firma en pad si es externo / no tiene firma registrada).
 */
class MovementRegisteredSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERER_SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private const RECEIVER_SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private const PAD_SIGNATURE = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private UserModel $admin;

    private UserModel $receiver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $this->admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();

        $this->receiver = UserModel::create([
            'name'      => 'Enfermera Receptora',
            'email'     => 'receptora@test.com',
            'password'  => bcrypt('Test1234!'),
            'is_active' => true,
        ]);
        $this->receiver->assignRole('personal_medico');

        RateLimiter::clear('signature-pin:' . $this->admin->id);
    }

    private function auth(UserModel $user): array
    {
        Auth::forgetGuards();
        $token = $user->createToken('test', $user->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    /**
     * Registra la firma de un usuario (sin pasar por HTTP). Devuelve el PIN
     * en el primer registro y null en actualizaciones.
     */
    private function registerSignature(UserModel $user, string $document, string $signature): ?string
    {
        return app(SaveMySignatureUseCase::class)->execute($user->id, new SaveSignatureData(
            documentType: IdentityDocumentType::CC,
            documentNumber: $document,
            signatureData: $signature,
        ))['pin'];
    }

    /** Un PIN con formato válido que no pertenece a ninguna firma. */
    private function unusedPin(): string
    {
        $pins = app(SignaturePinService::class);

        for ($n = 0; $n < 10 ** SignaturePinService::PIN_LENGTH; $n++) {
            $pin = str_pad((string) $n, SignaturePinService::PIN_LENGTH, '0', STR_PAD_LEFT);
            if (! UserSignatureModel::where('pin_lookup', $pins->lookup($pin))->exists()) {
                return $pin;
            }
        }

        $this->fail('No hay PIN libres para la prueba.');
    }

    /** Crea una salida interna pendiente de firma con stock disponible. */
    private function createPendingExit(int $quantity = 10): array
    {
        $warehouse = WarehouseModel::create(['name' => 'Almacén Firmas', 'code' => 'ALM-FIR', 'is_active' => true]);
        $zone      = ZoneModel::create(['warehouse_id' => $warehouse->id, 'name' => 'Zona Firmas', 'code' => 'Z-FIR', 'type' => 'ambient', 'is_active' => true]);
        $location  = LocationModel::create(['zone_id' => $zone->id, 'name' => 'Ubicación Firmas', 'code' => 'U-FIR', 'is_active' => true]);

        $generic = GenericProductModel::where('barcode', '000001')->firstOrFail();
        $variant = ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();

        $batch = BatchModel::create([
            'product_variant_id' => $variant->id,
            'lot_number'         => 'LOT-FIRMA',
            'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
            'quantity_received'  => 50,
            'quantity_available' => 50,
            'status'             => 'active',
            'received_at'        => now(),
        ]);
        $batch->locations()->attach($location->id, ['quantity' => 50]);

        $entry = MovementDocumentModel::create([
            'document_number' => 'ENT-FIRMA-' . uniqid(),
            'document_type'   => 'entry',
            'warehouse_id'    => $warehouse->id,
            'user_id'         => $this->admin->id,
            'status'          => 'confirmed',
        ]);
        StockMovementModel::create([
            'movement_document_id' => $entry->id,
            'warehouse_id'         => $warehouse->id,
            'product_variant_id'   => $variant->id,
            'batch_id'             => $batch->id,
            'location_to_id'       => $location->id,
            'movement_type'        => 'entry',
            'quantity'             => 50,
            'user_id'              => $this->admin->id,
        ]);
        app(\App\Modules\Inventory\Domain\Services\StockCalculator::class)->recalculateSummary($variant->id, $warehouse->id);

        $documentId = $this->withHeaders($this->auth($this->admin))
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $warehouse->id,
                'cost_center_id' => CostCenterModel::where('type', 'internal')->first()->id,
                'items'          => [['generic_product_id' => $generic->id, 'quantity' => $quantity, 'location_id' => $location->id]],
            ])
            ->assertStatus(201)
            ->json('data.id');

        return ['document_id' => $documentId, 'batch' => $batch];
    }

    private function confirm(int $documentId, array $payload)
    {
        return $this->withHeaders($this->auth($this->admin))
            ->postJson("/api/v1/movement-documents/{$documentId}/confirm", $payload);
    }

    public function test_entrega_con_firma_registrada_y_recibe_con_pin(): void
    {
        $this->registerSignature($this->admin, '1010101', self::DELIVERER_SIGNATURE);
        $pin = $this->registerSignature($this->receiver, '2020202', self::RECEIVER_SIGNATURE);

        ['document_id' => $documentId, 'batch' => $batch] = $this->createPendingExit(10);

        $this->confirm($documentId, ['received_by' => ['pin' => $pin]])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('movement_signatures', [
            'movement_document_id' => $documentId,
            'role'                 => 'delivered_by',
            'method'               => 'stored',
            'user_id'              => $this->admin->id,
            'signer_name'          => $this->admin->name,
            'signer_document'      => 'CC 1010101',
            'signature_data'       => self::DELIVERER_SIGNATURE,
        ]);
        $this->assertDatabaseHas('movement_signatures', [
            'movement_document_id' => $documentId,
            'role'                 => 'received_by',
            'method'               => 'pin',
            'user_id'              => $this->receiver->id,
            'signer_name'          => 'Enfermera Receptora',
            'signer_document'      => 'CC 2020202',
            'signature_data'       => self::RECEIVER_SIGNATURE,
        ]);

        $this->assertSame(40.0, (float) $batch->refresh()->quantity_available);
    }

    public function test_receptor_externo_firma_en_pad(): void
    {
        $this->registerSignature($this->admin, '1010101', self::DELIVERER_SIGNATURE);
        ['document_id' => $documentId] = $this->createPendingExit();

        $this->confirm($documentId, ['received_by' => [
            'name'      => 'Proveedor Externo',
            'document'  => '900123456',
            'signature' => self::PAD_SIGNATURE,
        ]])->assertStatus(200);

        $this->assertDatabaseHas('movement_signatures', [
            'movement_document_id' => $documentId,
            'role'                 => 'received_by',
            'method'               => 'pad',
            'user_id'              => null,
            'signer_name'          => 'Proveedor Externo',
            'signature_data'       => self::PAD_SIGNATURE,
        ]);
    }

    public function test_flujo_original_con_ambas_firmas_en_pad_sigue_funcionando(): void
    {
        ['document_id' => $documentId] = $this->createPendingExit();

        $this->confirm($documentId, [
            'delivered_by' => ['name' => 'Entrega Manual', 'document' => '123', 'signature' => self::PAD_SIGNATURE],
            'received_by'  => ['name' => 'Recibe Manual', 'document' => '456', 'signature' => self::PAD_SIGNATURE],
        ])->assertStatus(200);

        $this->assertDatabaseHas('movement_signatures', ['movement_document_id' => $documentId, 'role' => 'delivered_by', 'method' => 'pad']);
        $this->assertDatabaseHas('movement_signatures', ['movement_document_id' => $documentId, 'role' => 'received_by', 'method' => 'pad']);
    }

    public function test_entrega_sin_firma_registrada_retorna_409_y_no_mueve_stock(): void
    {
        $pin = $this->registerSignature($this->receiver, '2020202', self::RECEIVER_SIGNATURE);
        ['document_id' => $documentId, 'batch' => $batch] = $this->createPendingExit(10);

        $this->confirm($documentId, ['received_by' => ['pin' => $pin]])
            ->assertStatus(409)
            ->assertJsonPath('message', 'No tiene una firma registrada. Regístrela en Mi perfil → Mi firma o firme manualmente.');

        $this->assertDatabaseHas('movement_documents', ['id' => $documentId, 'status' => 'pending_signature']);
        $this->assertDatabaseCount('movement_signatures', 0);
        $this->assertSame(50.0, (float) $batch->refresh()->quantity_available);
    }

    public function test_pin_invalido_retorna_422_y_no_confirma(): void
    {
        $this->registerSignature($this->admin, '1010101', self::DELIVERER_SIGNATURE);
        $pin = $this->registerSignature($this->receiver, '2020202', self::RECEIVER_SIGNATURE);
        ['document_id' => $documentId] = $this->createPendingExit();

        $wrongPin = $this->unusedPin();

        $this->confirm($documentId, ['received_by' => ['pin' => $wrongPin]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['received_by.pin']);

        $this->assertDatabaseHas('movement_documents', ['id' => $documentId, 'status' => 'pending_signature']);
    }

    public function test_pin_con_formato_invalido_falla_validacion(): void
    {
        ['document_id' => $documentId] = $this->createPendingExit();

        $this->confirm($documentId, ['received_by' => ['pin' => '1a']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['received_by.pin']);
    }

    public function test_pin_de_usuario_inactivo_es_rechazado(): void
    {
        $this->registerSignature($this->admin, '1010101', self::DELIVERER_SIGNATURE);
        $pin = $this->registerSignature($this->receiver, '2020202', self::RECEIVER_SIGNATURE);
        $this->receiver->update(['is_active' => false]);

        ['document_id' => $documentId] = $this->createPendingExit();

        $this->confirm($documentId, ['received_by' => ['pin' => $pin]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['received_by.pin']);
    }

    public function test_quien_entrega_no_puede_recibir_con_su_propio_pin(): void
    {
        $ownPin = $this->registerSignature($this->admin, '1010101', self::DELIVERER_SIGNATURE);
        ['document_id' => $documentId] = $this->createPendingExit();

        $this->confirm($documentId, ['received_by' => ['pin' => $ownPin]])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Quien recibe no puede ser la misma persona que entrega.');
    }

    public function test_se_bloquea_tras_superar_los_intentos_de_pin_fallidos(): void
    {
        $this->registerSignature($this->admin, '1010101', self::DELIVERER_SIGNATURE);
        $pin = $this->registerSignature($this->receiver, '2020202', self::RECEIVER_SIGNATURE);
        ['document_id' => $documentId] = $this->createPendingExit();

        $wrongPin = $this->unusedPin();

        for ($i = 0; $i < RegisteredSignatureResolver::MAX_PIN_ATTEMPTS; $i++) {
            $this->confirm($documentId, ['received_by' => ['pin' => $wrongPin]])->assertStatus(422);
        }

        // Aun con el PIN correcto queda bloqueado durante la ventana
        $this->confirm($documentId, ['received_by' => ['pin' => $pin]])->assertStatus(429);
        $this->assertDatabaseHas('movement_documents', ['id' => $documentId, 'status' => 'pending_signature']);
    }

    public function test_el_comprobante_conserva_la_firma_aunque_el_usuario_la_cambie_despues(): void
    {
        $this->registerSignature($this->admin, '1010101', self::DELIVERER_SIGNATURE);
        $pin = $this->registerSignature($this->receiver, '2020202', self::RECEIVER_SIGNATURE);
        ['document_id' => $documentId] = $this->createPendingExit();

        $this->confirm($documentId, ['received_by' => ['pin' => $pin]])->assertStatus(200);

        // El receptor actualiza su firma: el comprobante ya emitido no cambia
        $this->registerSignature($this->receiver, '3030303', self::PAD_SIGNATURE);

        $this->withHeaders($this->auth($this->admin))
            ->getJson("/api/v1/movement-documents/{$documentId}/signature/received_by")
            ->assertStatus(200)
            ->assertJsonPath('data.method', 'pin')
            ->assertJsonPath('data.signer_document', 'CC 2020202')
            ->assertJsonPath('data.signature_data', self::RECEIVER_SIGNATURE);
    }

    public function test_pdf_del_comprobante_se_genera_con_firmas_registradas(): void
    {
        $this->registerSignature($this->admin, '1010101', self::DELIVERER_SIGNATURE);
        $pin = $this->registerSignature($this->receiver, '2020202', self::RECEIVER_SIGNATURE);
        ['document_id' => $documentId] = $this->createPendingExit();

        $this->confirm($documentId, ['received_by' => ['pin' => $pin]])->assertStatus(200);

        $this->withHeaders($this->auth($this->admin))
            ->get("/api/v1/movement-documents/{$documentId}/pdf")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
