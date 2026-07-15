<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Audit\Infrastructure\Traits\Auditable;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovementModel extends Model
{
    use Auditable;

    public const UPDATED_AT = null;

    protected $table = 'stock_movements';

    protected $fillable = [
        'movement_document_id',
        'warehouse_id',
        'warehouse_to_id',
        'product_variant_id',
        'batch_id',
        'location_from_id',
        'location_to_id',
        'movement_type',
        'quantity',
        'reason',
        'reference_type',
        'reference_id',
        'cost_center_id',
        'service_id',
        'patient_document',
        'patient_external_id',
        'invoice_number',
        'entry_temperature',
        'user_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status'     => MovementStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariantModel::class, 'product_variant_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchModel::class, 'batch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }

    public function warehouseTo(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_to_id');
    }

    public function locationFrom(): BelongsTo
    {
        return $this->belongsTo(LocationModel::class, 'location_from_id');
    }

    public function locationTo(): BelongsTo
    {
        return $this->belongsTo(LocationModel::class, 'location_to_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenterModel::class, 'cost_center_id');
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalServiceModel::class, 'service_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(MovementDocumentModel::class, 'movement_document_id');
    }
}
