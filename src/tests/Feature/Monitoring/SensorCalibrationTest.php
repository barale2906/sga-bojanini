<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Monitoring\Infrastructure\Notifications\SensorCalibrationAlertNotification;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorCalibrationCertificateModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorReadingModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Certificados de calibración de equipos de monitoreo, último registro
 * del equipo, datos de zona/sede en el listado y exportación a Excel.
 */
class SensorCalibrationTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $admin;

    private SensorModel $sensor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('monitoreo');

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\MonitoringSeeder']);

        $this->admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->sensor = SensorModel::where('code', 'TEMP-ZR01-01')->first();
    }

    private function auth(?UserModel $user = null): array
    {
        Auth::forgetGuards();
        $user ??= $this->admin;
        $token = $user->createToken('test', $user->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function uploadCertificate(int $sensorId, string $expiresAt, string $number = 'CAL-001')
    {
        return $this->withHeaders($this->auth())->post("/api/v1/sensors/{$sensorId}/calibrations", [
            'file'               => UploadedFile::fake()->create('certificado.pdf', 120, 'application/pdf'),
            'expires_at'         => $expiresAt,
            'calibrated_at'      => now()->subDays(10)->toDateString(),
            'certificate_number' => $number,
            'laboratory'         => 'Metrología S.A.S.',
        ]);
    }

    public function test_crear_sensor_con_certificado_lo_deja_vigente(): void
    {
        $zone = ZoneModel::where('code', 'ZR01')->first();

        $response = $this->withHeaders($this->auth())->post('/api/v1/sensors', [
            'zone_id'                => $zone->id,
            'name'                   => 'Termohigrómetro Nevera 2',
            'type'                   => 'temperature',
            'unit'                   => '°C',
            'certificate_file'       => UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf'),
            'certificate_expires_at' => now()->addYear()->toDateString(),
            'certificate_laboratory' => 'Metrología S.A.S.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.calibration.status', 'valid')
            ->assertJsonPath('data.zone.warehouse_name', 'Almacén Principal');

        $certificate = SensorCalibrationCertificateModel::where('sensor_id', $response->json('data.id'))->first();
        $this->assertTrue($certificate->is_current);
        Storage::disk('monitoreo')->assertExists($certificate->file_path);
    }

    public function test_crear_sensor_sin_certificado_queda_como_sin_certificado(): void
    {
        $zone = ZoneModel::where('code', 'ZR01')->first();

        $this->withHeaders($this->auth())->postJson('/api/v1/sensors', [
            'zone_id' => $zone->id,
            'name'    => 'Higrómetro Bodega',
            'type'    => 'humidity',
            'unit'    => '%',
        ])->assertCreated()->assertJsonPath('data.calibration.status', 'missing');
    }

    public function test_fecha_de_vencimiento_sin_archivo_es_rechazada(): void
    {
        $zone = ZoneModel::where('code', 'ZR01')->first();

        $this->withHeaders($this->auth())->postJson('/api/v1/sensors', [
            'zone_id'                => $zone->id,
            'name'                   => 'Higrómetro Bodega',
            'type'                   => 'humidity',
            'unit'                   => '%',
            'certificate_expires_at' => now()->addYear()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('certificate_file');

        $this->assertDatabaseMissing('sensors', ['name' => 'Higrómetro Bodega']);
    }

    public function test_nuevo_certificado_conserva_el_anterior_como_historial(): void
    {
        $this->uploadCertificate($this->sensor->id, now()->addDays(5)->toDateString(), 'CAL-OLD')->assertCreated();
        $this->uploadCertificate($this->sensor->id, now()->addYear()->toDateString(), 'CAL-NEW')
            ->assertCreated()
            ->assertJsonPath('data.is_current', true)
            ->assertJsonPath('data.status', 'valid');

        $history = $this->withHeaders($this->auth())->getJson("/api/v1/sensors/{$this->sensor->id}/calibrations")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $history);
        $this->assertSame('CAL-NEW', $history[0]['certificate_number']);
        $this->assertTrue($history[0]['is_current']);
        $this->assertSame('CAL-OLD', $history[1]['certificate_number']);
        $this->assertFalse($history[1]['is_current']);
        $this->assertSame('replaced', $history[1]['status']);
        $this->assertNotNull($history[1]['replaced_at']);
    }

    public function test_retirar_el_vigente_restaura_el_anterior(): void
    {
        $this->uploadCertificate($this->sensor->id, now()->addDays(20)->toDateString(), 'CAL-OLD');
        $newId = $this->uploadCertificate($this->sensor->id, now()->addYear()->toDateString(), 'CAL-NEW')->json('data.id');

        $this->withHeaders($this->auth())->deleteJson("/api/v1/calibrations/{$newId}")->assertOk();

        $current = SensorCalibrationCertificateModel::where('sensor_id', $this->sensor->id)->where('is_current', true)->get();
        $this->assertCount(1, $current);
        $this->assertSame('CAL-OLD', $current->first()->certificate_number);
        $this->assertSoftDeleted('sensor_calibration_certificates', ['id' => $newId]);
    }

    public function test_descarga_del_certificado(): void
    {
        $id = $this->uploadCertificate($this->sensor->id, now()->addYear()->toDateString())->json('data.id');

        $this->withHeaders($this->auth())->get("/api/v1/calibrations/{$id}/download")
            ->assertOk()
            ->assertDownload('certificado.pdf');
    }

    public function test_usuario_sin_acceso_al_equipo_no_puede_cargar_certificado(): void
    {
        $user = UserModel::create([
            'name' => 'Jefe sin almacén', 'email' => 'jefe.sin@test.com',
            'password' => bcrypt('Test1234!'), 'is_active' => true,
        ]);
        $user->assignRole('jefe_almacen');

        Auth::forgetGuards();
        $this->withHeaders($this->auth($user))->post("/api/v1/sensors/{$this->sensor->id}/calibrations", [
            'file'       => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
            'expires_at' => now()->addYear()->toDateString(),
        ])->assertForbidden();
    }

    public function test_listado_incluye_zona_sede_calibracion_y_ultimo_registro(): void
    {
        SensorReadingModel::where('sensor_id', $this->sensor->id)->delete();
        SensorReadingModel::create(['sensor_id' => $this->sensor->id, 'value' => 4.1, 'reading_source' => 'manual', 'recorded_at' => now()->subHours(5), 'user_id' => $this->admin->id]);
        SensorReadingModel::create(['sensor_id' => $this->sensor->id, 'value' => 5.3, 'reading_source' => 'manual', 'recorded_at' => now()->subHour(), 'user_id' => $this->admin->id]);

        $sensors = collect($this->withHeaders($this->auth())->getJson('/api/v1/sensors')->assertOk()->json('data'));
        $row = $sensors->firstWhere('id', $this->sensor->id);

        $this->assertSame('Zona Refrigerada 01', $row['zone']['name']);
        $this->assertSame('Almacén Principal', $row['zone']['warehouse_name']);
        $this->assertEquals(2.0, $row['zone']['temp_min']);
        $this->assertSame('missing', $row['calibration']['status']);
        $this->assertEquals(5.3, $row['last_reading']['value']);
        $this->assertSame($this->admin->name, $row['last_reading']['user_name']);

        $this->withHeaders($this->auth())->getJson("/api/v1/sensors/{$this->sensor->id}/readings/latest")
            ->assertOk()
            ->assertJsonPath('data.value', 5.3);
    }

    public function test_editar_sensor_persiste_el_estado_activo(): void
    {
        $this->withHeaders($this->auth())->putJson("/api/v1/sensors/{$this->sensor->id}", [
            'zone_id'   => $this->sensor->zone_id,
            'name'      => $this->sensor->name,
            'type'      => $this->sensor->type,
            'unit'      => $this->sensor->unit,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);
    }

    public function test_exportar_lecturas_a_excel(): void
    {
        Excel::fake();

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $this->withHeaders($this->auth())
            ->get("/api/v1/sensors/{$this->sensor->id}/readings/export?date_from={$from}&date_to={$to}")
            ->assertOk();

        Excel::assertDownloaded(
            'lecturas_temp-zr01-01_' . str_replace('-', '', $from) . '_' . str_replace('-', '', $to) . '.xlsx',
        );
    }

    public function test_exportar_genera_el_xlsx_con_las_lecturas(): void
    {
        SensorReadingModel::create(['sensor_id' => $this->sensor->id, 'value' => 9.5, 'reading_source' => 'manual', 'recorded_at' => now()->subHour(), 'user_id' => $this->admin->id]);
        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $response = $this->withHeaders($this->auth())
            ->get("/api/v1/sensors/{$this->sensor->id}/readings/export?date_from={$from}&date_to={$to}")
            ->assertOk();

        $path = $response->baseResponse->getFile()->getPathname();
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
        $flat = collect($sheet)->flatten()->filter()->map(fn ($v) => (string) $v);

        $this->assertTrue($flat->contains('Almacén Principal'));
        $this->assertTrue($flat->contains('Fuera de rango'));
        $this->assertTrue($flat->contains($this->admin->name));
    }

    public function test_exportar_valida_el_periodo(): void
    {
        $this->withHeaders($this->auth())
            ->getJson("/api/v1/sensors/{$this->sensor->id}/readings/export?date_from=2026-11-10&date_to=2026-11-01")
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    }

    public function test_alerta_de_calibracion_en_hitos_y_vencidos(): void
    {
        Notification::fake();
        $other = SensorModel::where('code', 'HUM-ZR01-01')->first();

        // 15 días → hito; 14 días → no es hito.
        $this->uploadCertificate($this->sensor->id, now()->addDays(15)->toDateString());
        $this->uploadCertificate($other->id, now()->addDays(14)->toDateString());

        $this->artisan('sga:check-sensor-calibrations')->assertSuccessful();

        Notification::assertSentTo($this->admin, SensorCalibrationAlertNotification::class, function ($n) {
            $data = $n->toDatabase($this->admin);

            return count($data['expiring']) === 1 && $data['expired'] === [] && $data['severity'] === 'warning';
        });

        // Vencido hace 7 días → recordatorio semanal.
        Notification::fake();
        SensorCalibrationCertificateModel::where('sensor_id', $other->id)->update(['expires_at' => now()->subDays(7)->toDateString()]);
        SensorCalibrationCertificateModel::where('sensor_id', $this->sensor->id)->update(['expires_at' => now()->addDays(40)->toDateString()]);

        $this->artisan('sga:check-sensor-calibrations')->assertSuccessful();

        Notification::assertSentTo($this->admin, SensorCalibrationAlertNotification::class, function ($n) {
            $data = $n->toDatabase($this->admin);

            return count($data['expired']) === 1 && $data['expiring'] === [] && $data['severity'] === 'critical';
        });
    }
}
