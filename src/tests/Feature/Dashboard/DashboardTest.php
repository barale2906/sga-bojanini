<?php

namespace Tests\Feature\Dashboard;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    public function test_dashboard_inventory_endpoint(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/inventory')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_products',
                    'stock_ok_count',
                    'stock_low_count',
                    'stock_critical_count',
                    'expiring_today',
                    'movements_today',
                ],
            ]);
    }

    public function test_dashboard_purchasing_endpoint(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/purchasing')
            ->assertOk()
            ->assertJsonStructure(['data' => ['pending_orders', 'approved_orders', 'total_month']]);
    }

    public function test_dashboard_monitoring_endpoint(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/monitoring')
            ->assertOk()
            ->assertJsonStructure(['data' => ['active_sensors', 'readings_today']]);
    }

    public function test_dashboard_activity_endpoint(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/activity')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_dashboard_alerts_endpoint(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/alerts')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
