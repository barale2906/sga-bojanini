<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovementDocumentModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'movement_documents';

    protected $fillable = [
        'document_number',
        'document_type',
        'warehouse_id',
        'warehouse_to_id',
        'cost_center_id',
        'service_id',
        'patient_document',
        'patient_external_id',
        'invoice_number',
        'entry_temperature',
        'reason',
        'movement_date',
        'user_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status'        => MovementStatus::class,
            'movement_date' => 'datetime',
            'created_at'    => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }

    public function warehouseTo(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_to_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenterModel::class, 'cost_center_id');
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalServiceModel::class, 'service_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovementModel::class, 'movement_document_id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(MovementSignatureModel::class, 'movement_document_id');
    }
}
