<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Purchasing\Domain\Services\ApprovalEngine;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalFlowModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalStepModel;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PurchasingSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = SupplierModel::firstOrCreate(
            ['tax_id' => '900123456-1'],
            [
                'name'         => 'Proveedor Médico Demo',
                'contact_name' => 'Contacto Compras',
                'phone'        => '3001234567',
                'email'        => 'compras@proveedor.demo',
                'is_active'    => true,
            ],
        );

        $aguja = ProductModel::where('code', 'AGU-21G')->first();

        if ($aguja !== null) {
            $aguja->suppliers()->syncWithoutDetaching([
                $supplier->id => [
                    'lead_time_days' => 7,
                    'is_preferred'   => true,
                    'unit_price'     => 150000,
                ],
            ]);
        }

        $superAdminRole = Role::where('name', 'super_administrador')->first();

        if ($superAdminRole === null) {
            return;
        }

        $flow = ApprovalFlowModel::firstOrCreate(
            [
                'name'        => 'Aprobación estándar OC',
                'entity_type' => ApprovalEngine::ENTITY_PURCHASE_ORDER,
            ],
            [
                'conditions' => null,
                'is_active'  => true,
            ],
        );

        ApprovalStepModel::firstOrCreate(
            [
                'approval_flow_id' => $flow->id,
                'step_order'       => 1,
            ],
            [
                'role_id'     => $superAdminRole->id,
                'is_required' => true,
            ],
        );
    }
}
