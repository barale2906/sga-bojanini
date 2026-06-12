<?php

namespace Tests\Unit\Purchasing;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Purchasing\Domain\Services\ApprovalEngine;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalFlowModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalStepModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);
        $this->engine = app(ApprovalEngine::class);
    }

    public function test_single_level_approval(): void
    {
        $order = $this->createPurchaseOrder('OC-UNIT-1', 100000);

        $records = $this->engine->initiateApproval(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id, [
            'total_amount' => 100000,
        ]);

        $this->assertCount(1, $records);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->engine->processDecision($records[0]->id, $admin->id, 'approved');

        $this->assertTrue($this->engine->isFullyApproved(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id));
        $this->assertFalse($this->engine->isRejected(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id));
    }

    public function test_multi_level_approval(): void
    {
        ApprovalFlowModel::where('name', 'Aprobación estándar OC')->update(['is_active' => false]);

        $managerRole = Role::where('name', 'jefe_almacen')->firstOrFail();
        $adminRole = Role::where('name', 'super_administrador')->firstOrFail();

        $flow = ApprovalFlowModel::create([
            'name'        => 'Flujo dos niveles',
            'entity_type' => ApprovalEngine::ENTITY_PURCHASE_ORDER,
            'conditions'  => ['min_amount' => 50000],
            'is_active'   => true,
        ]);

        ApprovalStepModel::create([
            'approval_flow_id' => $flow->id,
            'step_order'       => 1,
            'role_id'          => $managerRole->id,
            'is_required'      => true,
        ]);

        ApprovalStepModel::create([
            'approval_flow_id' => $flow->id,
            'step_order'       => 2,
            'role_id'          => $adminRole->id,
            'is_required'      => true,
        ]);

        $order = $this->createPurchaseOrder('OC-UNIT-2', 200000);

        $records = $this->engine->initiateApproval(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id, [
            'total_amount' => 200000,
        ]);

        $this->assertCount(2, $records);
        $this->assertFalse($this->engine->isFullyApproved(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id));

        $manager = UserModel::create([
            'name' => 'Manager', 'email' => 'manager@test.local',
            'password' => bcrypt('x'), 'is_active' => true,
        ]);
        $manager->assignRole('jefe_almacen');
        $this->engine->processDecision($records[0]->id, $manager->id, 'approved');

        $this->assertFalse($this->engine->isFullyApproved(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id));

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->engine->processDecision($records[1]->id, $admin->id, 'approved');

        $this->assertTrue($this->engine->isFullyApproved(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id));
    }

    public function test_rejection_stops_flow(): void
    {
        $order = $this->createPurchaseOrder('OC-UNIT-3', 50000);

        $records = $this->engine->initiateApproval(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id, [
            'total_amount' => 50000,
        ]);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $this->engine->processDecision($records[0]->id, $admin->id, 'rejected', 'No autorizado');

        $this->assertTrue($this->engine->isRejected(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id));
        $this->assertFalse($this->engine->isFullyApproved(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id));
    }

    private function createPurchaseOrder(string $code, float $total): PurchaseOrderModel
    {
        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $warehouse = WarehouseModel::create(['name' => 'Alm Test', 'code' => 'ALM-'.$code, 'is_active' => true]);

        return PurchaseOrderModel::create([
            'code'         => $code,
            'supplier_id'  => SupplierModel::firstOrFail()->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'pending_approval',
            'total_amount' => $total,
            'created_by'   => $admin->id,
        ]);
    }
}
