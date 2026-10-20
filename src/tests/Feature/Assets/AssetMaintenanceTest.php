<?php

namespace Tests\Feature\Assets;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** Planes, cronograma, ejecución y soportes de mantenimiento. */
class AssetMaintenanceTest extends AssetTestCase
{
    private SupplierModel $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = SupplierModel::create(['name' => 'BioService SAS', 'tax_id' => '900111222', 'is_active' => true, 'supplier_type' => 'both']);
    }

    private function createPlan(int $assetId, array $overrides = []): array
    {
        return $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$assetId}/maintenance-plans", array_merge([
            'maintenance_type'  => 'preventive',
            'frequency_value'   => 6,
            'frequency_unit'    => 'months',
            'start_date'        => now()->addDays(5)->toDateString(),
            'performed_by_type' => 'external',
            'supplier_id'       => $this->supplier->id,
        ], $overrides))->assertCreated()->json('data');
    }

    private function openOrder(int $assetId): AssetMaintenanceModel
    {
        return AssetMaintenanceModel::where('asset_id', $assetId)->whereIn('status', ['scheduled', 'in_progress'])->firstOrFail();
    }

    private function completePayload(array $overrides = []): array
    {
        return array_merge([
            'performed_at'             => now()->subHour()->toDateTimeString(),
            'performed_by_type'        => 'external',
            'supplier_id'              => $this->supplier->id,
            'external_technician_name' => 'Carlos Pérez',
            'activities_performed'     => 'Limpieza, verificación eléctrica y prueba de alarmas.',
            'result'                   => 'operational',
        ], $overrides);
    }

    public function test_crear_plan_programa_la_primera_orden(): void
    {
        $asset = $this->createAsset();
        $plan  = $this->createPlan($asset['id']);

        $this->assertSame(now()->addDays(5)->toDateString(), $plan['next_date']);
        $this->assertSame('Cada 6 meses', $plan['frequency_label']);
        $this->assertSame(1, AssetMaintenanceModel::where('plan_id', $plan['id'])->where('status', 'scheduled')->count());
    }

    public function test_plan_con_inicio_pasado_programa_la_siguiente_fecha_futura(): void
    {
        $asset = $this->createAsset();
        $plan  = $this->createPlan($asset['id'], [
            'start_date' => now()->subDays(25)->toDateString(), 'frequency_value' => 10, 'frequency_unit' => 'days',
        ]);

        $this->assertSame(now()->addDays(5)->toDateString(), $plan['next_date']);
    }

    public function test_editar_plan_reprograma_y_desactivar_cancela_la_orden(): void
    {
        $asset = $this->createAsset();
        $plan  = $this->createPlan($asset['id']);

        $this->withHeaders($this->auth())->putJson("/api/v1/assets/maintenance-plans/{$plan['id']}", [
            'maintenance_type' => 'preventive', 'frequency_value' => 3, 'frequency_unit' => 'months',
            'start_date' => now()->addDays(20)->toDateString(), 'performed_by_type' => 'external', 'supplier_id' => $this->supplier->id,
        ])->assertOk()->assertJsonPath('data.next_date', now()->addDays(20)->toDateString());

        $this->withHeaders($this->auth())->putJson("/api/v1/assets/maintenance-plans/{$plan['id']}", [
            'maintenance_type' => 'preventive', 'frequency_value' => 3, 'frequency_unit' => 'months',
            'start_date' => now()->addDays(20)->toDateString(), 'performed_by_type' => 'external', 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame(0, AssetMaintenanceModel::where('plan_id', $plan['id'])->where('status', 'scheduled')->count());
    }

    public function test_correctivo_no_se_planifica_y_exige_falla(): void
    {
        $asset = $this->createAsset();

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenance-plans", [
            'maintenance_type' => 'corrective', 'frequency_value' => 1, 'frequency_unit' => 'months',
            'start_date' => now()->toDateString(), 'performed_by_type' => 'internal',
        ])->assertUnprocessable();

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenances", [
            'maintenance_type' => 'corrective', 'scheduled_date' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('reported_failure');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenances", [
            'maintenance_type' => 'corrective', 'scheduled_date' => now()->toDateString(), 'reported_failure' => 'No enciende',
        ])->assertCreated()->assertJsonPath('data.status', 'scheduled')->assertJsonPath('data.plan_id', null);
    }

    public function test_activo_general_no_admite_mantenimientos(): void
    {
        $asset = $this->createAsset('MUE');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenances", [
            'maintenance_type' => 'inspection', 'scheduled_date' => now()->toDateString(),
        ])->assertStatus(409);
    }

    public function test_iniciar_pone_el_activo_en_mantenimiento(): void
    {
        $asset = $this->createAsset();
        $this->createPlan($asset['id']);
        $order = $this->openOrder($asset['id']);

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/start")
            ->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->assertSame('maintenance', AssetModel::find($asset['id'])->status->value);
    }

    public function test_registrar_exige_quien_lo_ejecuto(): void
    {
        $asset = $this->createAsset();
        $this->createPlan($asset['id']);
        $order = $this->openOrder($asset['id']);

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/complete", $this->completePayload([
            'external_technician_name' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors('external_technician_name');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/complete", $this->completePayload([
            'performed_by_type' => 'internal', 'supplier_id' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors('technician_id');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/complete", $this->completePayload([
            'activities_performed' => '',
        ]))->assertUnprocessable()->assertJsonValidationErrors('activities_performed');
    }

    public function test_registrar_sin_soportes_guarda_quien_registro_y_programa_la_siguiente(): void
    {
        $asset = $this->createAsset();
        $plan  = $this->createPlan($asset['id']);
        $order = $this->openOrder($asset['id']);

        $data = $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/complete", $this->completePayload([
            'result'         => 'out_of_service',
            'parts_replaced' => [['name' => 'Batería', 'quantity' => 1, 'cost' => 85000]],
        ]))->assertOk()->json('data');

        $this->assertSame('completed', $data['status']);
        $this->assertSame($this->admin->id, $data['registered_by']['id']);
        $this->assertSame('Carlos Pérez', $data['external_technician_name']);
        $this->assertSame([], $data['documents']);
        $this->assertSame('out_of_service', AssetModel::find($asset['id'])->status->value);

        $next = AssetMaintenanceModel::where('plan_id', $plan['id'])->where('status', 'scheduled')->firstOrFail();
        $this->assertSame(now()->subHour()->addMonths(6)->toDateString(), $next->scheduled_date->toDateString());

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/complete", $this->completePayload())
            ->assertStatus(409);
    }

    public function test_registrar_con_firma_del_tecnico_externo_y_recibido_por_pin(): void
    {
        $receiver = $this->userWith(['activos.ver'], 'enfermeria@test.local');
        $pin      = $this->registerSignature($receiver, '3030303030');

        $asset = $this->createAsset();
        $order = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenances", [
            'maintenance_type' => 'corrective', 'scheduled_date' => now()->toDateString(), 'reported_failure' => 'Alarma intermitente',
        ])->json('data');

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order['id']}/complete", $this->completePayload([
            'delivered_by' => $this->padSigner('Carlos Pérez'),
            'received_by'  => ['pin' => $pin],
        ]))->assertOk()->assertJsonCount(2, 'data.signatures');

        $this->withHeaders($this->auth())->get("/api/v1/assets/maintenances/{$order['id']}/report")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_cancelar_en_ejecucion_devuelve_operativo_y_programa_siguiente(): void
    {
        $asset = $this->createAsset();
        $plan  = $this->createPlan($asset['id']);
        $order = $this->openOrder($asset['id']);

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/start")->assertOk();
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/cancel", ['reason' => 'Proveedor no asistió'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('operational', AssetModel::find($asset['id'])->status->value);
        $this->assertSame(1, AssetMaintenanceModel::where('plan_id', $plan['id'])->where('status', 'scheduled')->count());
    }

    public function test_reprogramar_solo_ordenes_programadas(): void
    {
        $asset = $this->createAsset();
        $this->createPlan($asset['id']);
        $order = $this->openOrder($asset['id']);

        $this->withHeaders($this->auth())->putJson("/api/v1/assets/maintenances/{$order->id}", ['scheduled_date' => now()->addDays(9)->toDateString()])
            ->assertOk()->assertJsonPath('data.scheduled_date', now()->addDays(9)->toDateString());

        $this->withHeaders($this->auth())->postJson("/api/v1/assets/maintenances/{$order->id}/start");
        $this->withHeaders($this->auth())->putJson("/api/v1/assets/maintenances/{$order->id}", ['scheduled_date' => now()->toDateString()])
            ->assertStatus(409);
    }

    public function test_cronograma_filtra_vencidos(): void
    {
        $asset = $this->createAsset();
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenances", [
            'maintenance_type' => 'inspection', 'scheduled_date' => now()->subDays(3)->toDateString(),
        ]);
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenances", [
            'maintenance_type' => 'inspection', 'scheduled_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->withHeaders($this->auth())->getJson('/api/v1/assets/maintenances?overdue=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.is_overdue', true);

        $this->withHeaders($this->auth())->getJson('/api/v1/assets/maintenances?from=' . now()->toDateString())
            ->assertJsonPath('meta.total', 1);
    }

    public function test_soportes_opcionales_se_cargan_descargan_y_eliminan(): void
    {
        Storage::fake('local');

        $asset = $this->createAsset();
        $order = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenances", [
            'maintenance_type' => 'calibration', 'scheduled_date' => now()->toDateString(),
        ])->json('data');

        $doc = $this->withHeaders($this->auth())->post("/api/v1/assets/{$asset['id']}/documents", [
            'file'           => UploadedFile::fake()->create('certificado.pdf', 120, 'application/pdf'),
            'document_type'  => 'calibration_certificate',
            'title'          => 'Certificado de calibración 2026',
            'maintenance_id' => $order['id'],
            'expires_at'     => now()->addYear()->toDateString(),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        Storage::disk('local')->assertExists("assets/{$asset['id']}/" . basename(\App\Modules\Assets\Infrastructure\Persistence\Models\AssetDocumentModel::find($doc['id'])->file_path));

        $this->withHeaders($this->auth())->getJson("/api/v1/assets/{$asset['id']}/documents?maintenance_id={$order['id']}")
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withHeaders($this->auth())->get("/api/v1/assets/documents/{$doc['id']}/download")->assertOk();

        $this->withHeaders($this->auth())->deleteJson("/api/v1/assets/documents/{$doc['id']}")->assertOk();
        $this->assertSoftDeleted('asset_documents', ['id' => $doc['id']]);
    }

    public function test_soporte_rechaza_tipos_no_permitidos_y_mantenimiento_ajeno(): void
    {
        Storage::fake('local');

        $asset = $this->createAsset();
        $other = $this->createAsset();
        $order = $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$other['id']}/maintenances", [
            'maintenance_type' => 'inspection', 'scheduled_date' => now()->toDateString(),
        ])->json('data');

        $this->withHeaders($this->auth())->post("/api/v1/assets/{$asset['id']}/documents", [
            'file' => UploadedFile::fake()->create('virus.exe', 10), 'document_type' => 'other', 'title' => 'x',
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->withHeaders($this->auth())->post("/api/v1/assets/{$asset['id']}/documents", [
            'file' => UploadedFile::fake()->image('foto.jpg'), 'document_type' => 'photo', 'title' => 'Foto', 'maintenance_id' => $order['id'],
        ], ['Accept' => 'application/json'])->assertStatus(409);
    }

    public function test_alertas_notifican_a_usuarios_con_permiso(): void
    {
        Notification::fake();

        $planner = $this->userWith(['activos_mantenimientos.programar'], 'planner@test.local');
        $asset   = $this->createAsset('BIO', ['warranty_until' => now()->addDays(10)->toDateString()]);
        $this->withHeaders($this->auth())->postJson("/api/v1/assets/{$asset['id']}/maintenances", [
            'maintenance_type' => 'inspection', 'scheduled_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('sga:check-asset-alerts')->assertSuccessful();

        Notification::assertSentTo($planner, \App\Modules\Assets\Infrastructure\Notifications\AssetMaintenanceAlertNotification::class);
        Notification::assertSentTo($planner, \App\Modules\Assets\Infrastructure\Notifications\AssetExpirationAlertNotification::class);
    }

    public function test_exporta_cronograma_e_inventario(): void
    {
        $asset = $this->createAsset();
        $this->createPlan($asset['id']);

        $this->withHeaders($this->auth())->get('/api/v1/assets/reports/maintenance-schedule')->assertOk()->assertDownload();
        $this->withHeaders($this->auth())->get('/api/v1/assets/reports/inventory')->assertOk()->assertDownload();
    }
}
