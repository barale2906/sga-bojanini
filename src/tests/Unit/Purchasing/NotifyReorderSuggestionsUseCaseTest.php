<?php

declare(strict_types=1);

namespace Tests\Unit\Purchasing;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Purchasing\Application\UseCases\NotifyReorderSuggestionsUseCase;
use App\Modules\Purchasing\Infrastructure\Notifications\ReorderSuggestionsNotification;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyReorderSuggestionsUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PurchasingSeeder']);
    }

    public function test_sends_single_summary_when_there_are_suggestions(): void
    {
        Notification::fake();

        $this->lowStockProducts(2);

        $result = app(NotifyReorderSuggestionsUseCase::class)->execute();

        $this->assertSame(2, $result['products']);

        // Un solo resumen por destinatario, no uno por producto.
        $sent = Notification::sent(
            UserModel::role('super_administrador')->firstOrFail(),
            ReorderSuggestionsNotification::class,
        );
        $this->assertCount(1, $sent);
        Notification::assertSentTimes(ReorderSuggestionsNotification::class, UserModel::role(['administrador', 'jefe_almacen', 'compras', 'super_administrador'])->where('is_active', true)->count());
    }

    public function test_does_not_notify_without_suggestions(): void
    {
        Notification::fake();

        GenericProductModel::query()->update(['reorder_point' => 0]);

        $result = app(NotifyReorderSuggestionsUseCase::class)->execute();

        $this->assertSame(0, $result['products']);
        Notification::assertNothingSent();
    }

    private function lowStockProducts(int $count): void
    {
        $warehouse = WarehouseModel::create(['name' => 'Alm Notif', 'code' => 'ALM-NT', 'is_active' => true]);

        GenericProductModel::query()->update(['reorder_point' => 0]);

        foreach (GenericProductModel::limit($count)->get() as $generic) {
            $generic->update(['reorder_point' => 50, 'min_stock' => 20, 'is_active' => true]);
            $variant = ProductVariantModel::where('generic_product_id', $generic->id)->firstOrFail();

            StockSummaryModel::create([
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $warehouse->id,
                'total_quantity'     => 5,
                'available_quantity' => 5,
            ]);
        }
    }
}
