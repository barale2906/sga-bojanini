<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Assets\Domain\Enums\MaintenanceResult;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceType;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetMaintenanceModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'asset_maintenances';

    protected $fillable = [
        'asset_id', 'plan_id', 'maintenance_type', 'status', 'scheduled_date', 'started_at', 'performed_at',
        'performed_by_type', 'technician_id', 'supplier_id', 'external_technician_name', 'reported_failure',
        'activities_performed', 'findings', 'recommendations', 'parts_replaced', 'cost', 'result',
        'registered_by', 'created_by', 'cancelled_by', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_type'  => MaintenanceType::class,
            'status'            => MaintenanceStatus::class,
            'performed_by_type' => PerformedByType::class,
            'result'            => MaintenanceResult::class,
            'scheduled_date'    => 'date',
            'started_at'        => 'datetime',
            'performed_at'      => 'datetime',
            'parts_replaced'    => 'array',
            'cost'              => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'asset_id')->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenancePlanModel::class, 'plan_id')->withTrashed();
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'technician_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierModel::class, 'supplier_id')->withTrashed();
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'registered_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocumentModel::class, 'maintenance_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(AssetSignatureModel::class, 'signable_id')
            ->where('signable_type', AssetSignatureModel::SIGNABLE_MAINTENANCE);
    }
}
