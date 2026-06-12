<?php

namespace Tests\Feature\Integration;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Flujo end-to-end: lectura fuera de rango → alerta → notificación en BD.
 */
class ConditionAlertFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\MonitoringSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_condition_alert_flow_creates_notification(): void
    {
        $sensor = SensorModel::where('code', 'TEMP-ZR01-01')->firstOrFail();
        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();

        // Zona refrigerada del seeder: temp_max = 8 °C → 15 °C dispara out_of_range
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/sensors/{$sensor->id}/readings", [
                'value'          => 15.0,
                'reading_source' => 'manual',
                'recorded_at'    => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertStatus(201, $response->getContent())
            ->assertJsonPath('success', true);

        $alerts = $response->json('data.alerts');
        $this->assertNotEmpty($alerts, 'Se esperaba al menos una alerta en la respuesta JSON.');
        $this->assertSame('out_of_range', $alerts[0]['type']);

        $row = DB::table('notifications')
            ->where('notifiable_type', UserModel::class)
            ->where('notifiable_id', $admin->id)
            ->first();

        $this->assertNotNull($row, 'No se creó notificación en BD para el admin (revisar NotificationRecipientService y rol super_administrador).');

        $data = json_decode($row->data, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('condition_out_of_range', $data['type']);
    }
}
