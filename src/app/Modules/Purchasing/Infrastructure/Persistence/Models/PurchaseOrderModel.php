<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Persistence\Models;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderModel extends Model
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'supplier_id',
        'warehouse_id',
        'code',
        'status',
        'subtotal',
        'tax_amount',
        'total_amount',
        'notes',
        'expected_delivery_date',
        'created_by',
        'sent_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'               => 'decimal:2',
            'tax_amount'             => 'decimal:2',
            'total_amount'           => 'decimal:2',
            'expected_delivery_date' => 'date',
            'sent_at'                => 'datetime',
            'received_at'            => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierModel::class, 'supplier_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemModel::class, 'purchase_order_id');
    }
}
