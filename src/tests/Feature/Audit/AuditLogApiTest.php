<?php

namespace Tests\Feature\Audit;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $admin = UserModel::where('email', 'admin@sga.bojanini.com')->firstOrFail();
        Auth::login($admin);

        $category = CategoryModel::first();
        $unit     = UnitOfMeasureModel::where('abbreviation', 'UND')->first();
        ProductModel::create([
            'category_id'  => $category->id,
            'base_unit_id' => $unit->id,
            'product_type' => 'simple',
            'name'         => 'Producto Audit API',
            'code'         => 'AUD-API-001',
        ]);

        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_index_returns_audit_logs(): void
    {
        $this->assertGreaterThan(0, AuditLogModel::count());

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_show_returns_single_audit_log(): void
    {
        $log = AuditLogModel::firstOrFail();

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $log->id);
    }

    public function test_summary_returns_counts(): void
    {
        $this->withHeaders($this->auth())
            ->getJson('/api/v1/audit-logs/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['total', 'by_action', 'by_type'],
            ]);
    }
}
