<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Assets\Domain\Enums\FrequencyUnit;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceType;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetMaintenancePlanModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'asset_maintenance_plans';

    protected $fillable = [
        'asset_id', 'maintenance_type', 'frequency_value', 'frequency_unit', 'start_date',
        'performed_by_type', 'technician_id', 'supplier_id', 'notes', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_type'  => MaintenanceType::class,
            'frequency_unit'    => FrequencyUnit::class,
            'performed_by_type' => PerformedByType::class,
            'frequency_value'   => 'integer',
            'start_date'        => 'date',
            'is_active'         => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'asset_id')->withTrashed();
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'technician_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierModel::class, 'supplier_id')->withTrashed();
    }

    /** Orden abierta (programada o en ejecución) que el plan tiene en el cronograma. */
    public function openOrder(): HasOne
    {
        return $this->hasOne(AssetMaintenanceModel::class, 'plan_id')
            ->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value]);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenanceModel::class, 'plan_id');
    }
}
