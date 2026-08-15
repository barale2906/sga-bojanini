<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Persistence\Models;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidatedPurchaseOrderItemModel extends Model
{
    protected $table = 'consolidated_purchase_order_items';

    protected $fillable = [
        'consolidated_order_id',
        'product_variant_id',
        'product_presentation_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity'    => 'decimal:3',
            'unit_price'  => 'decimal:2',
            'tax_rate'    => 'decimal:2',
            'tax_amount'  => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function consolidatedOrder(): BelongsTo
    {
        return $this->belongsTo(ConsolidatedPurchaseOrderModel::class, 'consolidated_order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariantModel::class, 'product_variant_id');
    }

    public function presentation(): BelongsTo
    {
        return $this->belongsTo(ProductPresentationModel::class, 'product_presentation_id');
    }
}
