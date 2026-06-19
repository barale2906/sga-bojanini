<?php

namespace Tests\Feature\Reports;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Shared\Infrastructure\Notifications\ReportReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $this->admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->token = $this->admin->createToken('test', $this->admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        // Fuerza la vía asíncrona sin depender del volumen real de datos seedeados.
        config(['sga.reports.async_row_threshold' => -1]);
    }

    /** @see \Tests\Feature\WarehouseAccessTest::headersFor() */
    private function headersFor(string $token): array
    {
        Auth::forgetGuards();

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_large_report_is_queued_and_notifies_user_when_ready(): void
    {
        Notification::fake();

        $response = $this->withHeaders($this->headersFor($this->token))
            ->getJson('/api/v1/reports/inventory?format=pdf');

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['mode', 'export_id', 'status']]);

        $exportId = $response->json('data.export_id');

        Notification::assertSentTo($this->admin, ReportReadyNotification::class);

        $status = $this->withHeaders($this->headersFor($this->token))
            ->getJson("/api/v1/reports/exports/{$exportId}");

        $status->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.is_ready', true);

        $download = $this->withHeaders($this->headersFor($this->token))
            ->get("/api/v1/reports/exports/{$exportId}/download");

        $download->assertStatus(200);
        $this->assertSame('application/pdf', $download->headers->get('Content-Type'));
    }

    public function test_cannot_download_another_users_export(): void
    {
        $other = UserModel::create([
            'name'      => 'Usuario Otro',
            'email'     => 'otro-reportes@example.com',
            'password'  => bcrypt('Test1234!'),
            'is_active' => true,
        ]);
        $other->givePermissionTo('reportes.ver');

        $response = $this->withHeaders($this->headersFor($this->token))
            ->getJson('/api/v1/reports/inventory?format=pdf');

        $exportId = $response->json('data.export_id');

        $otherToken = $other->createToken('test', $other->getAllPermissions()->pluck('name')->toArray())->plainTextToken;

        $this->withHeaders($this->headersFor($otherToken))
            ->getJson("/api/v1/reports/exports/{$exportId}")
            ->assertStatus(404);
    }
}
