<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Inventory\Domain\Events\BatchExpiringSoon;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Infrastructure\Listeners\NotifyExpiringBatch;
use App\Modules\Inventory\Infrastructure\Listeners\NotifyLowStock;
use App\Modules\Inventory\Infrastructure\Listeners\UpdateStockSummary;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(StockMovementCreated::class, UpdateStockSummary::class);
        Event::listen(BatchExpiringSoon::class, NotifyExpiringBatch::class);
        Event::listen(StockBelowReorderPoint::class, NotifyLowStock::class);

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->info->title = 'SGA Bojanini API';
                $openApi->info->version = config('scramble.info.version', '1.0.0');
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                );
            });
    }
}
