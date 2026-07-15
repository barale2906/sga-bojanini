<?php

namespace Tests\Feature\Audit;

use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuditableTraitTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $this->admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        Auth::login($this->admin);
    }

    public function test_create_product_generates_audit_log(): void
    {
        $category = CategoryModel::first();
        $unit     = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $product = GenericProductModel::create([
            'category_id'  => $category->id,
            'base_unit_id' => $unit->id,
            'product_type' => 'simple',
            'name'         => 'Ácido Hialurónico',
            'barcode'      => '000099',
        ]);

        $auditLog = AuditLogModel::where('auditable_type', 'generic_product')
            ->where('auditable_id', $product->id)
            ->where('action', 'create')
            ->first();

        $this->assertNotNull($auditLog, 'No se creó registro de auditoría');
        $this->assertEquals($this->admin->id, $auditLog->user_id);
        $this->assertNotNull($auditLog->new_values);
        $this->assertSame('Ácido Hialurónico', $auditLog->new_values['name']);
    }

    public function test_update_product_records_old_and_new_values(): void
    {
        $category = CategoryModel::first();
        $unit     = UnitOfMeasureModel::where('abbreviation', 'UND')->first();

        $product = GenericProductModel::create([
            'category_id'  => $category->id,
            'base_unit_id' => $unit->id,
            'product_type' => 'simple',
            'name'         => 'Producto Original',
            'barcode'      => '000098',
        ]);

        $product->update(['name' => 'Producto Modificado']);

        $auditLog = AuditLogModel::where('auditable_type', 'generic_product')
            ->where('action', 'update')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('Producto Original', $auditLog->old_values['name']);
        $this->assertEquals('Producto Modificado', $auditLog->new_values['name']);
    }
}
