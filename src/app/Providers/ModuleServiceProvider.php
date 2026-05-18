<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Persistence\EloquentUserRepository;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Persistence\EloquentLocationRepository;
use App\Modules\Warehouse\Infrastructure\Persistence\EloquentWarehouseRepository;
use App\Modules\Warehouse\Infrastructure\Persistence\EloquentZoneRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider que registra los bindings de TODOS los módulos.
 *
 * @see REF-13 en backend.md
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        // Auth (Fase 1)
        UserRepositoryInterface::class => EloquentUserRepository::class,

        // Warehouse (Fase 2)
        WarehouseRepositoryInterface::class => EloquentWarehouseRepository::class,
        ZoneRepositoryInterface::class      => EloquentZoneRepository::class,
        LocationRepositoryInterface::class  => EloquentLocationRepository::class,

        // Catalog (Fase 3)
        // CategoryRepositoryInterface::class    => EloquentCategoryRepository::class,
        // UnitOfMeasureRepositoryInterface::class => EloquentUnitOfMeasureRepository::class,
        // ProductRepositoryInterface::class     => EloquentProductRepository::class,
        // SupplierRepositoryInterface::class    => EloquentSupplierRepository::class,

        // Inventory (Fase 4)
        // BatchRepositoryInterface::class       => EloquentBatchRepository::class,
        // MovementRepositoryInterface::class    => EloquentMovementRepository::class,
        // StockSummaryRepositoryInterface::class => EloquentStockSummaryRepository::class,

        // Purchasing (Fase 5)
        // PurchaseOrderRepositoryInterface::class => EloquentPurchaseOrderRepository::class,
        // ApprovalFlowRepositoryInterface::class  => EloquentApprovalFlowRepository::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
