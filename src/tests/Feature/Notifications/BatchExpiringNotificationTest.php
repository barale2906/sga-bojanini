<?php

namespace Tests\Feature\Notifications;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Domain\Events\BatchExpiringSoon;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchExpiringNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_expiring_soon_event_creates_database_notification(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\CatalogSeeder']);

        $admin = UserModel::where('email', 'alexanderbarajas@gmail.com')->firstOrFail();
        $product = ProductModel::where('code', 'AGU-21G')->firstOrFail();

        $batch = BatchModel::create([
            'product_id'         => $product->id,
            'lot_number'         => 'LOT-NOTIF',
            'expiration_date'    => now()->addDays(5)->format('Y-m-d'),
            'quantity_received'  => 10,
            'quantity_available' => 10,
            'status'             => 'active',
            'received_at'        => now(),
        ]);

        event(new BatchExpiringSoon(
            batchId: $batch->id,
            productId: $product->id,
            lotNumber: $batch->lot_number,
            expirationDate: $batch->expiration_date->format('Y-m-d'),
            daysUntilExpiry: 5,
        ));

        $admin->refresh();
        $notification = $admin->notifications->first();

        $this->assertNotNull($notification);
        $this->assertSame('batch_expiring_soon', $notification->data['type']);
    }
}
