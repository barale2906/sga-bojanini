<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensorReadingTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sga.iot.token' => 'test-iot-token']);

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\MonitoringSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_store_reading_within_range_no_alert(): void
    {
        $sensor = SensorModel::where('code', 'TEMP-ZR01-01')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/sensors/{$sensor->id}/readings", [
                'value'          => 5.0,
                'reading_source' => 'manual',
                'recorded_at'    => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.alerts', []);
    }

    public function test_store_reading_out_of_range_generates_alert(): void
    {
        $sensor = SensorModel::where('code', 'TEMP-ZR01-01')->first();

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/sensors/{$sensor->id}/readings", [
                'value'          => 12.0,
                'reading_source' => 'manual',
                'recorded_at'    => now()->format('Y-m-d H:i:s'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $alerts = $response->json('data.alerts');
        $this->assertNotEmpty($alerts);
        $this->assertSame('out_of_range', $alerts[0]['type']);
    }

    public function test_bulk_readings_via_iot(): void
    {
        $response = $this->withHeader('X-IoT-Token', 'test-iot-token')
            ->postJson('/api/v1/sensors/readings/bulk', [
                'readings' => [
                    [
                        'sensor_code' => 'TEMP-ZR01-01',
                        'value'       => 4.9,
                        'recorded_at' => now()->format('Y-m-d H:i:s'),
                    ],
                    [
                        'sensor_code' => 'HUM-ZR01-01',
                        'value'       => 51.0,
                        'recorded_at' => now()->format('Y-m-d H:i:s'),
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.saved', 2);
    }

    public function test_bulk_readings_without_token_returns_401(): void
    {
        $this->postJson('/api/v1/sensors/readings/bulk', [
            'readings' => [
                [
                    'sensor_code' => 'TEMP-ZR01-01',
                    'value'       => 4.9,
                    'recorded_at' => now()->format('Y-m-d H:i:s'),
                ],
            ],
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_generate_condition_pdf(): void
    {
        $sensor = SensorModel::where('code', 'TEMP-ZR01-01')->first();

        $response = $this->withHeaders($this->auth())
            ->post('/api/v1/monitoring/reports/generate', [
                'sensor_id' => $sensor->id,
                'date_from' => now()->subDays(7)->format('Y-m-d'),
                'date_to'   => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertNotEmpty($response->getContent());
    }
}
