<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetLocationHistoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;

/**
 * Traslados: la firma es obligatoria solo cuando el activo sale del edificio
 * (otra sede, reparación externa, préstamo o baja).
 */
class AssetTransferTest extends AssetTestCase
{
    private UserModel $receiver;

    private string $receiverPin;

    private string $adminPin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiver = UserModel::create([
            'name' => 'Jefe de Sede', 'email' => 'jefe.sede@test.local', 'password' => bcrypt('Test1234!'), 'is_active' => true,
        ]);

        $this->adminPin    = $this->registerSignature($this->admin, '1010101010');
        $this->receiverPin = $this->registerSignature($this->receiver, '2020202020');
    }

    public function test_traslado_interno_se_aplica_de_inmediato_sin_firma(): void
    {
        $asset  = $this->createAsset();
        $target = $this->place('Clínica', 'Consultorio 2');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'relocation', 'to_place_id' => $target->id, 'to_responsible_id' => $this->receiver->id,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'relocation')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.requires_signature', false);

        $model = AssetModel::find($asset['id']);
        $this->assertSame($target->id, $model->current_place_id);
        $this->assertSame($this->receiver->id, $model->responsible_id);

        $history = AssetLocationHistoryModel::where('asset_id', $asset['id'])->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertNotNull($history[0]->to_date);
        $this->assertNull($history[1]->to_date);
    }

    public function test_traslado_interno_guarda_firmas_opcionales(): void
    {
        $asset = $this->createAsset();

        $transfer = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type'        => 'relocation',
            'to_place_id' => $this->place('Clínica', 'Consultorio 2')->id,
            'received_by' => ['pin' => $this->receiverPin],
        ])->assertCreated()->json('data');

        $this->assertSame('confirmed', $transfer['status']);
        $this->assertCount(2, $transfer['signatures']);
        $this->assertArrayNotHasKey('signature_data', $transfer['signatures'][0]);
    }

    public function test_cambio_a_otra_sede_queda_pendiente_hasta_firmar(): void
    {
        $asset  = $this->createAsset();
        $origin = $asset['location']['place_id'];
        $target = $this->place('Edificio 2', 'Call center');

        $transfer = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'relocation', 'to_place_id' => $target->id,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'site_transfer')
            ->assertJsonPath('data.status', 'pending_signature')
            ->json('data');

        $this->assertSame($origin, AssetModel::find($asset['id'])->current_place_id);

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'relocation', 'to_place_id' => $this->place('Clínica', 'Consultorio 3')->id,
        ])->assertStatus(409);

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/transfers/{$transfer['id']}/confirm", [
            'received_by' => ['pin' => $this->receiverPin],
        ])->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->assertSame($target->id, AssetModel::find($asset['id'])->current_place_id);
        $this->assertDatabaseHas('asset_signatures', ['signable_type' => 'transfer', 'signable_id' => $transfer['id'], 'role' => 'received_by', 'method' => 'pin', 'user_id' => $this->receiver->id]);
        $this->assertDatabaseHas('asset_signatures', ['signable_type' => 'transfer', 'signable_id' => $transfer['id'], 'role' => 'delivered_by', 'method' => 'stored', 'user_id' => $this->admin->id]);
    }

    public function test_confirmar_exige_firma_de_quien_recibe_y_pin_valido(): void
    {
        $asset    = $this->createAsset();
        $transfer = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'external_repair', 'external_destination' => 'Taller BioService',
        ])->assertCreated()->json('data');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/transfers/{$transfer['id']}/confirm", [])
            ->assertUnprocessable()->assertJsonValidationErrors('received_by');

        $wrongPin = collect(range(0, 9))->map(fn ($n) => str_repeat((string) $n, 3))
            ->first(fn ($pin) => ! in_array($pin, [$this->receiverPin, $this->adminPin], true));
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/transfers/{$transfer['id']}/confirm", [
            'received_by' => ['pin' => $wrongPin],
        ])->assertUnprocessable();

        $this->assertSame('pending_signature', $this->withHeaders($this->auth())->getJson("/api/v1/assets/transfers/{$transfer['id']}")->json('data.status'));
    }

    public function test_reparacion_externa_y_retorno(): void
    {
        $asset = $this->createAsset();

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type'                 => 'external_repair',
            'external_destination' => 'Taller BioService',
            'expected_return_date' => now()->addDays(10)->toDateString(),
            'received_by'          => $this->padSigner('Técnico BioService'),
        ])->assertCreated()->assertJsonPath('data.status', 'confirmed');

        $model = AssetModel::find($asset['id']);
        $this->assertTrue($model->is_off_premises);
        $this->assertNull($model->current_place_id);
        $this->assertSame('maintenance', $model->status->value);
        $this->assertSame('Taller BioService', $model->off_premises_location);

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'relocation', 'to_place_id' => $this->place()->id,
        ])->assertStatus(409);

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'return', 'to_place_id' => $this->place()->id, 'resulting_status' => 'operational',
        ])->assertCreated()->assertJsonPath('data.status', 'confirmed');

        $model->refresh();
        $this->assertFalse($model->is_off_premises);
        $this->assertSame('operational', $model->status->value);
        $this->assertSame(3, AssetLocationHistoryModel::where('asset_id', $asset['id'])->count());
    }

    public function test_prestamo_exige_destino(): void
    {
        $asset = $this->createAsset();

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", ['type' => 'loan'])
            ->assertUnprocessable()->assertJsonValidationErrors('external_destination');
    }

    public function test_baja_requiere_permiso_motivo_y_firma(): void
    {
        $asset   = $this->createAsset();
        $mover   = $this->userWith(['activos.ver', 'activos_traslados.solicitar'], 'mover@test.local');

        $this->withHeaders($this->auth($mover))->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'retirement', 'reason' => 'Obsoleto',
        ])->assertForbidden();

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", ['type' => 'retirement'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenance-plans", [
            'maintenance_type' => 'preventive', 'frequency_value' => 6, 'frequency_unit' => 'months',
            'start_date' => now()->addMonth()->toDateString(), 'performed_by_type' => 'external',
        ])->assertCreated();

        $transfer = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'retirement', 'reason' => 'Obsoleto', 'external_destination' => 'Gestor RAEE',
        ])->assertCreated()->assertJsonPath('data.status', 'pending_signature')->json('data');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/transfers/{$transfer['id']}/confirm", [
            'received_by' => $this->padSigner('Gestor RAEE'),
        ])->assertOk();

        $model = AssetModel::find($asset['id']);
        $this->assertSame('retired', $model->status->value);
        $this->assertSame('Obsoleto', $model->retirement_reason);
        $this->assertDatabaseHas('asset_maintenance_plans', ['asset_id' => $asset['id'], 'is_active' => false]);
        $this->assertDatabaseMissing('asset_maintenances', ['asset_id' => $asset['id'], 'status' => 'scheduled']);

        $this->withHeaders($this->auth())->putJson("/api/v1/assets/{$asset['id']}", ['name' => 'X', 'category_id' => $asset['category']['id']])
            ->assertStatus(409);
    }

    public function test_cancelar_traslado_pendiente(): void
    {
        $asset    = $this->createAsset();
        $transfer = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'loan', 'external_destination' => 'Hospital vecino',
        ])->json('data');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/transfers/{$transfer['id']}/cancel", ['reason' => 'Ya no se presta'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/transfers/{$transfer['id']}/cancel", ['reason' => 'otra vez'])
            ->assertStatus(409);
        $this->assertFalse(AssetModel::find($asset['id'])->is_off_premises);
    }

    public function test_traslado_sin_cambios_responde_409(): void
    {
        $asset = $this->createAsset();

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'relocation', 'to_place_id' => $asset['location']['place_id'],
        ])->assertStatus(409);
    }

    public function test_lista_traslados_pendientes_y_acta_pdf(): void
    {
        $asset    = $this->createAsset();
        $transfer = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'loan', 'external_destination' => 'Hospital vecino',
        ])->json('data');

        $this->withHeaders($this->auth())->getJson('/api/v1/assets/transfers?status=pending_signature')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $transfer['id']);

        $this->withHeaders($this->auth())->get("/api/v1/assets/transfers/{$transfer['id']}/act")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_historial_de_ubicaciones_expone_tramos(): void
    {
        $asset = $this->createAsset();
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/transfers", [
            'type' => 'relocation', 'to_place_id' => $this->place('Clínica', 'Consultorio 2')->id,
        ]);

        $history = $this->withHeaders($this->auth())->getJson("/api/v1/assets/{$asset['id']}/location-history")->assertOk()->json('data');

        $this->assertCount(2, $history);
        $this->assertTrue($history[0]['is_current']);
        $this->assertSame('Clínica → Consultorio 2', $history[0]['location']);
        $this->assertSame('relocation', $history[0]['transfer_type']);
    }
}
