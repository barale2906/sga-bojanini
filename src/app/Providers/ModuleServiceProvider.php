<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Persistence\EloquentUserRepository;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductSanitaryRegistrationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\SupplierRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentCategoryRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductClassificationRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductKitComponentRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductPresentationRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentProductSanitaryRegistrationRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentSupplierRepository;
use App\Modules\Catalog\Infrastructure\Persistence\EloquentUnitOfMeasureRepository;
use App\Modules\Audit\Domain\Repositories\AuditLogRepositoryInterface;
use App\Modules\Audit\Infrastructure\Persistence\EloquentAuditLogRepository;
use App\Modules\CostCenter\Domain\Repositories\CostCenterRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\ProcedurePriceRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\EloquentCostCenterRepository;
use App\Modules\CostCenter\Infrastructure\Persistence\EloquentMedicalServiceRepository;
use App\Modules\CostCenter\Infrastructure\Persistence\EloquentPatientProcedureRecordRepository;
use App\Modules\CostCenter\Infrastructure\Persistence\EloquentProcedurePriceRepository;
use App\Modules\Integration\Domain\Ports\ClinicalRecordsServiceInterface;
use App\Modules\Integration\Domain\Ports\SchedulingServiceInterface;
use App\Modules\Integration\Domain\Repositories\ConsumptionRecordRepositoryInterface;
use App\Modules\Integration\Domain\Repositories\ExternalIntegrationRepositoryInterface;
use App\Modules\Integration\Infrastructure\ExternalServices\ClinicalRecordsApiAdapter;
use App\Modules\Integration\Infrastructure\ExternalServices\MockClinicalRecordsAdapter;
use App\Modules\Integration\Infrastructure\ExternalServices\MockSchedulingAdapter;
use App\Modules\Integration\Infrastructure\ExternalServices\SchedulingApiAdapter;
use App\Modules\Integration\Infrastructure\Persistence\EloquentConsumptionRecordRepository;
use App\Modules\Integration\Infrastructure\Persistence\EloquentExternalIntegrationRepository;
use App\Modules\Inventory\Domain\Repositories\StockSummaryRepositoryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\EloquentStockSummaryRepository;
use App\Modules\Monitoring\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorReadingRepositoryInterface;
use App\Modules\Monitoring\Domain\Repositories\SensorRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Persistence\EloquentAlertRuleRepository;
use App\Modules\Monitoring\Infrastructure\Persistence\EloquentSensorReadingRepository;
use App\Modules\Monitoring\Infrastructure\Persistence\EloquentSensorRepository;
use App\Modules\Warehouse\Domain\Repositories\CapacityRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\LocationRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Warehouse\Domain\Repositories\ZoneRepositoryInterface;
use App\Modules\Warehouse\Infrastructure\Persistence\EloquentCapacityRepository;
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
        CapacityRepositoryInterface::class  => EloquentCapacityRepository::class,

        // Catalog (Fase 3)
        CategoryRepositoryInterface::class                    => EloquentCategoryRepository::class,
        UnitOfMeasureRepositoryInterface::class               => EloquentUnitOfMeasureRepository::class,
        ProductRepositoryInterface::class                     => EloquentProductRepository::class,
        ProductClassificationRepositoryInterface::class       => EloquentProductClassificationRepository::class,
        ProductPresentationRepositoryInterface::class         => EloquentProductPresentationRepository::class,
        ProductKitComponentRepositoryInterface::class         => EloquentProductKitComponentRepository::class,
        ProductSanitaryRegistrationRepositoryInterface::class => EloquentProductSanitaryRegistrationRepository::class,
        SupplierRepositoryInterface::class                    => EloquentSupplierRepository::class,

        // CostCenter (Fase 10)
        CostCenterRepositoryInterface::class                  => EloquentCostCenterRepository::class,
        MedicalServiceRepositoryInterface::class              => EloquentMedicalServiceRepository::class,
        ProcedurePriceRepositoryInterface::class              => EloquentProcedurePriceRepository::class,
        PatientProcedureRecordRepositoryInterface::class      => EloquentPatientProcedureRecordRepository::class,

        // Inventory (Fase 4)
        // BatchRepositoryInterface::class       => EloquentBatchRepository::class,
        // MovementRepositoryInterface::class    => EloquentMovementRepository::class,
        StockSummaryRepositoryInterface::class => EloquentStockSummaryRepository::class,

        // Purchasing (Fase 5)
        // PurchaseOrderRepositoryInterface::class => EloquentPurchaseOrderRepository::class,
        // ApprovalFlowRepositoryInterface::class  => EloquentApprovalFlowRepository::class,

        // Monitoring (Fase 6)
        SensorRepositoryInterface::class        => EloquentSensorRepository::class,
        SensorReadingRepositoryInterface::class => EloquentSensorReadingRepository::class,
        AlertRuleRepositoryInterface::class     => EloquentAlertRuleRepository::class,

        // Audit (Fase 7)
        AuditLogRepositoryInterface::class => EloquentAuditLogRepository::class,

        // Integration (Fase 8)
        ExternalIntegrationRepositoryInterface::class => EloquentExternalIntegrationRepository::class,
        ConsumptionRecordRepositoryInterface::class   => EloquentConsumptionRecordRepository::class,
    ];

    public function register(): void
    {
        $useMock = (bool) config('sga.integrations.use_mock', true);

        $this->app->bind(SchedulingServiceInterface::class, $useMock
            ? MockSchedulingAdapter::class
            : SchedulingApiAdapter::class);

        $this->app->bind(ClinicalRecordsServiceInterface::class, $useMock
            ? MockClinicalRecordsAdapter::class
            : ClinicalRecordsApiAdapter::class);
    }

    public function boot(): void
    {
        //
    }
}
