<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSummaryModel extends Model
{
    public const CREATED_AT = null;

    protected $table = 'stock_summaries';

    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'total_quantity',
        'reserved_quantity',
        'available_quantity',
        'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'last_movement_at'   => 'datetime',
            'updated_at'         => 'datetime',
            'total_quantity'     => 'float',
            'reserved_quantity'  => 'float',
            'available_quantity' => 'float',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariantModel::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_id');
    }
}
