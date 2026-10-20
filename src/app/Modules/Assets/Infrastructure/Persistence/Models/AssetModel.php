<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence\Models;

use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetModel extends Model
{
    use Auditable;
    use SoftDeletes;

    protected $table = 'assets';

    protected $fillable = [
        'code', 'name', 'description', 'category_id', 'current_place_id', 'is_off_premises',
        'off_premises_location', 'responsible_id', 'brand', 'model', 'serial', 'acquisition_date',
        'acquisition_value', 'supplier_id', 'invoice_number', 'warranty_until', 'status',
        'retired_at', 'retirement_reason', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status'            => AssetStatus::class,
            'is_off_premises'   => 'boolean',
            'acquisition_date'  => 'date',
            'acquisition_value' => 'decimal:2',
            'warranty_until'    => 'date',
            'retired_at'        => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategoryModel::class, 'category_id')->withTrashed();
    }

    public function currentPlace(): BelongsTo
    {
        return $this->belongsTo(AssetPlaceModel::class, 'current_place_id')->withTrashed();
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'responsible_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierModel::class, 'supplier_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }

    public function technicalProfile(): HasOne
    {
        return $this->hasOne(AssetTechnicalProfileModel::class, 'asset_id');
    }

    public function locationHistory(): HasMany
    {
        return $this->hasMany(AssetLocationHistoryModel::class, 'asset_id')->orderByDesc('from_date')->orderByDesc('id');
    }

    public function currentLocation(): HasOne
    {
        return $this->hasOne(AssetLocationHistoryModel::class, 'asset_id')->whereNull('to_date');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AssetCommentModel::class, 'asset_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransferModel::class, 'asset_id')->orderByDesc('id');
    }

    public function pendingTransfer(): HasOne
    {
        return $this->hasOne(AssetTransferModel::class, 'asset_id')
            ->where('status', AssetTransferStatus::PendingSignature->value);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovementModel::class, 'asset_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(AssetMaintenancePlanModel::class, 'asset_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenanceModel::class, 'asset_id');
    }

    public function nextMaintenance(): HasOne
    {
        return $this->hasOne(AssetMaintenanceModel::class, 'asset_id')
            ->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value])
            ->orderBy('scheduled_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocumentModel::class, 'asset_id')->orderByDesc('id');
    }
}
