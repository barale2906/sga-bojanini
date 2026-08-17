<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Inventory\Infrastructure\Mail\MovementDocumentMail;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDocumentEmailTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CostCenterSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_envia_correo_a_destinatarios_validos(): void
    {
        Mail::fake();

        $documentId = $this->createConfirmedExitDocument();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/movement-documents/{$documentId}/send-email", [
                'recipients' => ['test@example.com', 'otro@example.com'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        Mail::assertQueued(MovementDocumentMail::class, function ($mail) use ($documentId) {
            return $mail->document->id === $documentId;
        });
    }

    public function test_falla_si_documento_no_esta_confirmado(): void
    {
        Mail::fake();

        $setup = $this->createWarehouseSetup();
        $variant = ProductVariantModel::whereHas('genericProduct', fn ($q) => $q->where('barcode', '000001'))->firstOrFail();
        $costCenter = CostCenterModel::first();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'  => $setup['warehouse']->id,
                'cost_center_id' => $costCenter->id,
                'items'         => [[
                    'generic_product_id' => $variant->generic_product_id,
                    'quantity'           => 1,
                ]],
            ]);

        // Primero registrar una entrada para tener stock
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [[
                    'product_variant_id' => $variant->id,
                    'location_id'        => $setup['location']->id,
                    'lot_number'         => 'LOT-EMAIL-001',
                    'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
                    'quantity_base'      => 50,
                ]],
            ]);

        $exitResponse = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $costCenter->id,
                'items'          => [[
                    'generic_product_id' => $variant->generic_product_id,
                    'quantity'           => 1,
                ]],
            ]);

        $documentId = $exitResponse->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/movement-documents/{$documentId}/send-email", [
                'recipients' => ['test@example.com'],
            ])
            ->assertStatus(409);

        Mail::assertNothingQueued();
    }

    public function test_requiere_al_menos_un_destinatario(): void
    {
        Mail::fake();

        $documentId = $this->createConfirmedExitDocument();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/movement-documents/{$documentId}/send-email", [
                'recipients' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Mail::assertNothingQueued();
    }

    public function test_rechaza_correo_con_formato_invalido(): void
    {
        Mail::fake();

        $documentId = $this->createConfirmedExitDocument();

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/movement-documents/{$documentId}/send-email", [
                'recipients' => ['no-es-un-correo'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Mail::assertNothingQueued();
    }

    public function test_falla_si_documento_no_existe(): void
    {
        Mail::fake();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movement-documents/99999/send-email', [
                'recipients' => ['test@example.com'],
            ])
            ->assertStatus(404);
    }

    private function createConfirmedExitDocument(): int
    {
        $setup = $this->createWarehouseSetup();
        $variant = ProductVariantModel::whereHas('genericProduct', fn ($q) => $q->where('barcode', '000001'))->firstOrFail();
        $costCenter = CostCenterModel::first();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/entry', [
                'warehouse_id' => $setup['warehouse']->id,
                'items'        => [[
                    'product_variant_id' => $variant->id,
                    'location_id'        => $setup['location']->id,
                    'lot_number'         => 'LOT-EMAIL-CONF',
                    'expiration_date'    => now()->addMonths(6)->format('Y-m-d'),
                    'quantity_base'      => 50,
                ]],
            ]);

        $exitResponse = $this->withHeaders($this->auth())
            ->postJson('/api/v1/movements/exit', [
                'warehouse_id'   => $setup['warehouse']->id,
                'cost_center_id' => $costCenter->id,
                'items'          => [[
                    'generic_product_id' => $variant->generic_product_id,
                    'quantity'           => 1,
                ]],
            ]);

        $documentId = $exitResponse->json('data.id');

        $this->confirmDocument($documentId);

        return $documentId;
    }

    private function confirmDocument(int $documentId): void
    {
        $minimalSignature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/movement-documents/{$documentId}/confirm", [
                'delivered_by' => [
                    'name'      => 'Test Entregador',
                    'document'  => '12345678',
                    'signature' => $minimalSignature,
                ],
                'received_by' => [
                    'name'      => 'Test Receptor',
                    'document'  => '87654321',
                    'signature' => $minimalSignature,
                ],
            ])
            ->assertStatus(200);
    }

    private function createWarehouseSetup(): array
    {
        $warehouse = WarehouseModel::create([
            'name'      => 'Almacén Email Test',
            'code'      => 'EML-01',
            'is_active' => true,
        ]);

        $zone = ZoneModel::create([
            'warehouse_id' => $warehouse->id,
            'name'         => 'Zona Email',
            'code'         => 'Z-EML-01',
            'type'         => 'ambient',
            'is_active'    => true,
        ]);

        $location = LocationModel::create([
            'zone_id'   => $zone->id,
            'name'      => 'Estante-EML-01',
            'code'      => 'EML-EST-01',
            'is_active' => true,
        ]);

        return compact('warehouse', 'zone', 'location');
    }
}
