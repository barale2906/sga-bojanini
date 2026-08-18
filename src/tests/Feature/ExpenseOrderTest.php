<?php

namespace Tests\Feature;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderPaymentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExpenseOrderTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->first();
        $this->token = $admin->createToken('test', $admin->getAllPermissions()->pluck('name')->toArray())->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function supplierWithEmail(): SupplierModel
    {
        $supplier = SupplierModel::where('email', 'compras@proveedor.demo')->first();
        $supplier->update(['supplier_type' => 'expense']);

        return $supplier;
    }

    private function defaultItems(): array
    {
        return [
            [
                'description' => 'Grabadora digital Sony ICD-PX470',
                'unit'        => 'und',
                'quantity'    => 3,
                'unit_price'  => 250000,
                'tax_rate'    => 19,
                'notes'       => 'Para área de recepción',
            ],
            [
                'description' => 'Uniforme pantalón azul talla 32',
                'unit'        => 'und',
                'quantity'    => 5,
                'unit_price'  => 85000,
                'tax_rate'    => 0,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────

    public function test_crear_orden_de_gasto(): void
    {
        $supplier = $this->supplierWithEmail();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id'            => $supplier->id,
                'expected_delivery_date' => now()->addDays(15)->format('Y-m-d'),
                'notes'                  => 'Equipos área administrativa',
                'items'                  => $this->defaultItems(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_type', 'expense')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.payment_status', 'unpaid');

        $data = $response->json('data');

        $this->assertStringStartsWith('OG-', $data['code']);
        $this->assertEquals(2, count($data['items']));
        $this->assertNull($data['warehouse_id'] ?? null);

        // Verifica totales calculados
        // Ítem 1: 3 × 250000 = 750000 + 19% = 142500 IVA → total 892500
        // Ítem 2: 5 × 85000  = 425000 + 0%  = 0 IVA → total 425000
        // Subtotal 1175000 | IVA 142500 | Total 1317500
        $this->assertEquals('1175000.00', $data['subtotal']);
        $this->assertEquals('142500.00', $data['tax_amount']);
        $this->assertEquals('1317500.00', $data['total_amount']);

        $this->assertDatabaseHas('purchase_orders', [
            'id'         => $data['id'],
            'order_type' => 'expense',
            'status'     => 'draft',
        ]);

        $this->assertDatabaseCount('expense_order_items', 2);
    }

    public function test_crear_orden_de_gasto_falla_sin_items(): void
    {
        $supplier = $this->supplierWithEmail();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => [],
            ])
            ->assertStatus(422);
    }

    public function test_listar_ordenes_de_gasto(): void
    {
        $supplier = $this->supplierWithEmail();

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $this->withHeaders($this->auth())
            ->getJson('/api/v1/expense-orders')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_ver_detalle_orden_de_gasto(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/expense-orders/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.order_type', 'expense');
    }

    public function test_actualizar_orden_en_draft(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $updated = $this->withHeaders($this->auth())
            ->putJson("/api/v1/expense-orders/{$id}", [
                'supplier_id' => $supplier->id,
                'notes'       => 'Nota actualizada',
                'items'       => [
                    [
                        'description' => 'Ítem único modificado',
                        'unit'        => 'global',
                        'quantity'    => 1,
                        'unit_price'  => 500000,
                        'tax_rate'    => 19,
                    ],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.notes', 'Nota actualizada');

        $this->assertDatabaseCount('expense_order_items', 1);
        $this->assertEquals('595000.00', $updated->json('data.total_amount'));
    }

    public function test_no_permite_actualizar_fuera_de_draft(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        // Enviar a aprobación
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/submit")
            ->assertStatus(200);

        // Intentar actualizar en pending_approval → error
        $this->withHeaders($this->auth())
            ->putJson("/api/v1/expense-orders/{$id}", [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_eliminar_orden_en_draft(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/expense-orders/{$id}")
            ->assertStatus(200);

        $this->assertDatabaseCount('expense_order_items', 0);
    }

    // ─────────────────────────────────────────────────────────────
    // FLUJO DE ESTADOS
    // ─────────────────────────────────────────────────────────────

    public function test_ciclo_completo_orden_gasto(): void
    {
        Mail::fake();

        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id     = $create->json('data.id');
        $itemId = $create->json('data.items.0.id');

        // draft → pending_approval
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_approval');

        // pending_approval → approved
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // approved → sent (envía email al proveedor)
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/send")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('purchase_orders', [
            'id'     => $id,
            'status' => 'sent',
        ]);
        $this->assertNotNull(PurchaseOrderModel::find($id)->sent_at);

        // sent → partially_received (recibe 2 de 3 del primer ítem)
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/receive", [
                'items' => [
                    ['item_id' => $itemId, 'quantity_received' => 2],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'partially_received');

        // partially_received → received (recibe el restante)
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/receive", [
                'items' => [
                    ['item_id' => $itemId, 'quantity_received' => 1],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'partially_received'); // 2do ítem aún pendiente

        // Ahora recibir el segundo ítem completo
        $itemId2 = $create->json('data.items.1.id');
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/receive", [
                'items' => [
                    ['item_id' => $itemId2, 'quantity_received' => 5],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'received');

        $this->assertNotNull(PurchaseOrderModel::find($id)->received_at);

        // Verificar que NO afectó inventario
        $this->assertDatabaseMissing('stock_summaries', ['warehouse_id' => null]);
    }

    public function test_rechazo_orden_gasto(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/submit")
            ->assertStatus(200);

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/reject", [
                'comments' => 'Excede presupuesto aprobado',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('purchase_orders', ['id' => $id, 'status' => 'rejected']);
    }

    public function test_cancelar_orden_aprobada(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())->postJson("/api/v1/expense-orders/{$id}/submit");
        $this->withHeaders($this->auth())->postJson("/api/v1/expense-orders/{$id}/approve");

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/cancel")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_no_permite_recibir_mas_de_lo_solicitado(): void
    {
        Mail::fake();
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => [[
                    'description' => 'Ítem único',
                    'unit'        => 'und',
                    'quantity'    => 2,
                    'unit_price'  => 100000,
                ]],
            ])
            ->assertStatus(201);

        $id     = $create->json('data.id');
        $itemId = $create->json('data.items.0.id');

        $this->withHeaders($this->auth())->postJson("/api/v1/expense-orders/{$id}/submit");
        $this->withHeaders($this->auth())->postJson("/api/v1/expense-orders/{$id}/approve");
        $this->withHeaders($this->auth())->postJson("/api/v1/expense-orders/{$id}/send");

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/receive", [
                'items' => [['item_id' => $itemId, 'quantity_received' => 5]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_no_permite_enviar_sin_email_de_proveedor(): void
    {
        // Proveedor sin email
        $supplier = SupplierModel::create([
            'name'          => 'Proveedor Sin Email',
            'tax_id'        => '123456789-0',
            'email'         => null,
            'is_active'     => true,
            'supplier_type' => 'expense',
        ]);

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())->postJson("/api/v1/expense-orders/{$id}/submit");
        $this->withHeaders($this->auth())->postJson("/api/v1/expense-orders/{$id}/approve");

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/send")
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────────────────────
    // PAGOS
    // ─────────────────────────────────────────────────────────────

    public function test_registrar_anticipo_en_cualquier_estado(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');
        // total_amount = 1317500

        // Anticipo en estado draft
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/payments", [
                'amount'         => 500000,
                'payment_date'   => now()->format('Y-m-d'),
                'payment_method' => 'transfer',
                'reference'      => 'TRF-001',
                'notes'          => 'Anticipo 38%',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.payment_status', 'partial')
            ->assertJsonPath('data.amount_paid', '500000.00');

        $this->assertDatabaseHas('purchase_order_payments', [
            'purchase_order_id' => $id,
            'amount'            => 500000,
            'payment_method'    => 'transfer',
        ]);
    }

    public function test_pago_total_actualiza_estado_paid(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => [[
                    'description' => 'Servicio de fumigación',
                    'unit'        => 'global',
                    'quantity'    => 1,
                    'unit_price'  => 300000,
                    'tax_rate'    => 0,
                ]],
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/payments", [
                'amount'         => 300000,
                'payment_date'   => now()->format('Y-m-d'),
                'payment_method' => 'cash',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.amount_paid', '300000.00');
    }

    public function test_listar_pagos_de_orden(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/payments", [
                'amount' => 200000, 'payment_date' => now()->format('Y-m-d'), 'payment_method' => 'cash',
            ]);

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/payments", [
                'amount' => 300000, 'payment_date' => now()->format('Y-m-d'), 'payment_method' => 'transfer', 'reference' => 'TRF-002',
            ]);

        $this->withHeaders($this->auth())
            ->getJson("/api/v1/expense-orders/{$id}/payments")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_eliminar_pago_recalcula_estado(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => [[
                    'description' => 'Obra civil oficina',
                    'unit'        => 'global',
                    'quantity'    => 1,
                    'unit_price'  => 1000000,
                    'tax_rate'    => 0,
                ]],
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        // Pagar completo
        $payment = $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/payments", [
                'amount' => 1000000, 'payment_date' => now()->format('Y-m-d'), 'payment_method' => 'check', 'reference' => 'CHQ-100',
            ])
            ->assertJsonPath('data.payment_status', 'paid');

        $paymentId = PurchaseOrderPaymentModel::where('purchase_order_id', $id)->first()->id;

        // Eliminar el pago → vuelve a unpaid
        $this->withHeaders($this->auth())
            ->deleteJson("/api/v1/expense-orders/{$id}/payments/{$paymentId}")
            ->assertStatus(200);

        $this->assertDatabaseHas('purchase_orders', [
            'id'             => $id,
            'payment_status' => 'unpaid',
            'amount_paid'    => 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // FACTURA Y CONTABILIDAD
    // ─────────────────────────────────────────────────────────────

    public function test_registrar_factura(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/invoice", [
                'invoice_number' => 'FV-2026-4821',
                'invoice_date'   => '2026-08-20',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.invoice_number', 'FV-2026-4821')
            ->assertJsonPath('data.invoice_date', '2026-08-20');

        $this->assertDatabaseHas('purchase_orders', [
            'id'             => $id,
            'invoice_number' => 'FV-2026-4821',
        ]);
    }

    public function test_enviar_a_contabilidad(): void
    {
        Mail::fake();

        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        // Registrar factura primero
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/invoice", [
                'invoice_number' => 'FV-2026-4821',
                'invoice_date'   => '2026-08-20',
            ]);

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/send-accounting", [
                'recipients' => ['contabilidad@clinica.com', 'finanzas@clinica.com'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull(PurchaseOrderModel::find($id)->accounting_sent_at);
    }

    public function test_no_permite_enviar_a_contabilidad_sin_factura(): void
    {
        $supplier = $this->supplierWithEmail();

        $create = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201);

        $id = $create->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$id}/send-accounting", [
                'recipients' => ['contabilidad@clinica.com'],
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // ─────────────────────────────────────────────────────────────
    // PROVEEDOR: BÚSQUEDA Y CREACIÓN RÁPIDA
    // ─────────────────────────────────────────────────────────────

    public function test_busqueda_proveedores_por_nombre(): void
    {
        SupplierModel::create(['name' => 'Uniformes Bojanini SAS', 'is_active' => true, 'supplier_type' => 'expense']);
        SupplierModel::create(['name' => 'Uniformes del Norte', 'is_active' => true, 'supplier_type' => 'expense']);
        SupplierModel::create(['name' => 'Proveedor Médico', 'is_active' => true, 'supplier_type' => 'inventory']);

        $result = $this->withHeaders($this->auth())
            ->getJson('/api/v1/suppliers/search?q=Uniforme&type=expense')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $result);
        $this->assertEquals('expense', $result[0]['supplier_type']);
    }

    public function test_busqueda_typeahead_requiere_al_menos_2_caracteres(): void
    {
        SupplierModel::create(['name' => 'Almacén General', 'is_active' => true, 'supplier_type' => 'expense']);

        // Con 1 carácter devuelve lista completa de tipo (sin filtro de texto)
        $result = $this->withHeaders($this->auth())
            ->getJson('/api/v1/suppliers/search?q=A&type=expense')
            ->assertStatus(200)
            ->json('data');

        // Al menos trae el proveedor creado
        $this->assertNotEmpty($result);
    }

    public function test_busqueda_incluye_proveedores_tipo_both(): void
    {
        SupplierModel::create(['name' => 'Proveedor Universal', 'is_active' => true, 'supplier_type' => 'both']);
        SupplierModel::create(['name' => 'Solo Inventario', 'is_active' => true, 'supplier_type' => 'inventory']);

        $result = $this->withHeaders($this->auth())
            ->getJson('/api/v1/suppliers/search?q=Prov&type=expense')
            ->assertStatus(200)
            ->json('data');

        $names = array_column($result, 'name');
        $this->assertContains('Proveedor Universal', $names);
        $this->assertNotContains('Solo Inventario', $names);
    }

    public function test_crear_proveedor_rapido_desde_orden_gasto(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders/supplier', [
                'name'  => 'Ferretería El Perno Feliz',
                'email' => 'ventas@perno.com',
                'phone' => '3109876543',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.supplier_type', 'expense')
            ->assertJsonPath('data.name', 'Ferretería El Perno Feliz');

        $this->assertDatabaseHas('suppliers', [
            'name'          => 'Ferretería El Perno Feliz',
            'supplier_type' => 'expense',
        ]);
    }

    public function test_crear_proveedor_rapido_solo_requiere_nombre(): void
    {
        $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders/supplier', [
                'name' => 'Servicios Varios SAS',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.supplier_type', 'expense');
    }

    public function test_no_permite_proveedor_de_inventario_en_orden_gasto(): void
    {
        $inventorySupplier = SupplierModel::create([
            'name'          => 'Proveedor Solo Inventario',
            'is_active'     => true,
            'supplier_type' => 'inventory',
        ]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $inventorySupplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_proveedor_tipo_both_valido_en_orden_gasto(): void
    {
        $bothSupplier = SupplierModel::create([
            'name'          => 'Proveedor Mixto',
            'email'         => 'mixto@proveedor.com',
            'is_active'     => true,
            'supplier_type' => 'both',
        ]);

        $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $bothSupplier->id,
                'items'       => $this->defaultItems(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_filtro_por_payment_status(): void
    {
        $supplier = $this->supplierWithEmail();

        $order1 = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => [[
                    'description' => 'Servicio A', 'unit' => 'und', 'quantity' => 1, 'unit_price' => 100000,
                ]],
            ])
            ->json('data.id');

        $order2 = $this->withHeaders($this->auth())
            ->postJson('/api/v1/expense-orders', [
                'supplier_id' => $supplier->id,
                'items'       => [[
                    'description' => 'Servicio B', 'unit' => 'und', 'quantity' => 1, 'unit_price' => 200000,
                ]],
            ])
            ->json('data.id');

        // Pagar order1 completo
        $this->withHeaders($this->auth())
            ->postJson("/api/v1/expense-orders/{$order1}/payments", [
                'amount' => 100000, 'payment_date' => now()->format('Y-m-d'), 'payment_method' => 'cash',
            ]);

        $paidList = $this->withHeaders($this->auth())
            ->getJson('/api/v1/expense-orders?payment_status=paid')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $paidList);
        $this->assertEquals($order1, $paidList[0]['id']);

        $unpaidList = $this->withHeaders($this->auth())
            ->getJson('/api/v1/expense-orders?payment_status=unpaid')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(1, $unpaidList);
        $this->assertEquals($order2, $unpaidList[0]['id']);
    }
}
